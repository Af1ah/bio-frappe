package main

import (
	"bytes"
	"context"
	"crypto/subtle"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"net"
	"net/http"
	"net/http/httptest"
	"os"
	"strconv"
	"strings"
	"sync"
	"time"

	zkadms "github.com/s0x90/zkteco-adms"
)

type gatewayConfig struct {
	deviceAddr       string
	managementAddr   string
	managementToken  string
	storePath        string
	trustedProxyNets []*net.IPNet
	laravel          laravelClient
}

type activeReceipt struct {
	id        string
	expected  int
	completed int
	done      chan struct{}
	closed    bool
	mu        sync.Mutex
}

type gateway struct {
	adms   *zkadms.ADMSServer
	store  *durableStore
	config gatewayConfig
	logger *slog.Logger
	active sync.Map
	locks  sync.Map
}

func loadConfig() gatewayConfig {
	managementToken := requiredEnv("ADMS_MANAGEMENT_TOKEN")
	laravelURL := requiredEnv("LARAVEL_INTERNAL_URL")
	laravelToken := requiredEnv("LARAVEL_GATEWAY_TOKEN")
	return gatewayConfig{
		deviceAddr: envOr("ADMS_DEVICE_ADDR", ":8080"), managementAddr: envOr("ADMS_MANAGEMENT_ADDR", ":8081"),
		managementToken: managementToken, storePath: envOr("ADMS_STORE_PATH", "/var/lib/bio-notifier-adms/gateway.db"),
		trustedProxyNets: parseCIDRs(os.Getenv("ADMS_TRUSTED_PROXY_CIDRS")),
		laravel:          newLaravelClient(laravelURL, laravelToken),
	}
}

func newGateway(config gatewayConfig, store *durableStore, logger *slog.Logger) *gateway {
	return &gateway{config: config, store: store, logger: logger}
}

func (g *gateway) serveDevice(w http.ResponseWriter, r *http.Request) {
	if r.Method == http.MethodGet && (r.URL.Path == "/" || r.URL.Path == "/health" || r.URL.Path == "/health/live") {
		writeJSON(w, http.StatusOK, map[string]string{"status": "ok", "service": "BIO-Notifier standalone ADMS", "device_endpoint": "/iclock/cdata"})
		return
	}
	if !strings.HasPrefix(r.URL.Path, "/iclock/") {
		http.NotFound(w, r)
		return
	}
	serial := strings.TrimSpace(r.URL.Query().Get("SN"))
	if serial == "" {
		http.Error(w, "missing device serial number", http.StatusBadRequest)
		return
	}
	binding, found, err := g.store.binding(serial)
	if err != nil || !found || !binding.Active {
		http.Error(w, "device is not registered", http.StatusForbidden)
		return
	}
	sourceIP, err := g.sourceIP(r)
	if err != nil || !ipAllowed(sourceIP, binding.SourceCIDR) {
		g.logger.Warn("device source rejected", "serial", serial, "source_ip", sourceIP, "error", err)
		http.Error(w, "device source is not authorized", http.StatusForbidden)
		return
	}
	if binding.SourceCIDR == "" || binding.SourceCIDR == "auto" {
		binding.SourceCIDR = sourceIP
		_ = g.store.putBinding(binding)
		g.logger.Info("device source IP auto-enrolled", "serial", serial, "source_ip", sourceIP)
	}
	body, err := io.ReadAll(io.LimitReader(r.Body, (5<<20)+1))
	if err != nil || len(body) > 5<<20 {
		http.Error(w, "request body too large", http.StatusRequestEntityTooLarge)
		return
	}
	receipt, existed, err := g.store.createReceipt(binding, sourceIP, r.Method, r.URL.Path, r.URL.Query(), body)
	if err != nil {
		http.Error(w, "failed to durably record request", http.StatusServiceUnavailable)
		return
	}
	if existed && (receipt.Status == "processed" || receipt.Status == "delivered") {
		writeStoredResponse(w, receipt)
		return
	}
	lock := g.deviceLock(serial)
	lock.Lock()
	defer lock.Unlock()

	active := &activeReceipt{id: receipt.ID, expected: -1, done: make(chan struct{})}
	g.active.Store(serial, active)
	defer g.active.Delete(serial)
	// Some ZKTeco/eSSL firmware polls the legacy .aspx spelling. The ADMS
	// library accepts the canonical iclock paths only, so normalize solely for
	// protocol handling. The original path remains in the durable receipt.
	protocolRequest := r.Clone(r.Context())
	protocolURL := *r.URL
	protocolURL.Path = canonicalADMSPath(r.URL.Path)
	protocolURL.RawPath = ""
	protocolRequest.URL = &protocolURL
	protocolRequest.Body = io.NopCloser(bytes.NewReader(body))
	recorder := httptest.NewRecorder()
	g.adms.ServeHTTP(recorder, protocolRequest)
	g.markDeliveredCommands(serial, receipt.ID, recorder.Body.String())
	active.setExpected(callbackCount(protocolRequest, body, recorder.Body.String()))
	select {
	case <-active.done:
	case <-time.After(3 * time.Second):
		g.logger.Warn("callback persistence wait timed out", "receipt_id", receipt.ID, "serial", serial)
	}
	responseBody := append([]byte(nil), recorder.Body.Bytes()...)
	_ = g.store.updateReceipt(receipt.ID, func(stored *gatewayReceipt) error {
		stored.Status = "processed"
		stored.ResponseStatus = recorder.Code
		stored.ResponseBody = responseBody
		return nil
	})
	copyResponse(w, recorder.Code, recorder.Header(), responseBody)
}

func (g *gateway) serveManagement(w http.ResponseWriter, r *http.Request) {
	if !secureBearer(r.Header.Get("Authorization"), g.config.managementToken) {
		http.Error(w, "unauthorized", http.StatusUnauthorized)
		return
	}
	switch {
	case r.Method == http.MethodGet && r.URL.Path == "/health/ready":
		if err := g.store.ready(); err != nil {
			http.Error(w, "store unavailable", http.StatusServiceUnavailable)
			return
		}
		w.WriteHeader(http.StatusNoContent)
	case r.Method == http.MethodPost && r.URL.Path == "/internal/v1/devices":
		g.registerDevice(w, r)
	case r.Method == http.MethodPost && r.URL.Path == "/internal/v1/commands":
		g.queueCommand(w, r)
	case r.Method == http.MethodGet && strings.HasPrefix(r.URL.Path, "/internal/v1/commands/"):
		id := strings.TrimPrefix(r.URL.Path, "/internal/v1/commands/")
		command, found, err := g.store.command(id)
		if err != nil || !found {
			http.NotFound(w, r)
			return
		}
		writeJSON(w, http.StatusOK, command)
	default:
		http.NotFound(w, r)
	}
}

func (g *gateway) registerDevice(w http.ResponseWriter, r *http.Request) {
	var input deviceRequest
	if decodeJSON(r, &input) != nil || input.BindingID == "" || input.TenantID == "" || input.SerialNumber == "" || input.OwnershipVersion < 1 {
		http.Error(w, "invalid device binding", http.StatusBadRequest)
		return
	}
	loc, err := time.LoadLocation(input.Timezone)
	if err != nil {
		http.Error(w, "invalid timezone", http.StatusUnprocessableEntity)
		return
	}
	if input.SourceCIDR == "" {
		http.Error(w, "source_cidr is required", http.StatusUnprocessableEntity)
		return
	}
	if _, _, err := net.ParseCIDR(normalizeCIDR(input.SourceCIDR)); err != nil {
		http.Error(w, "invalid source_cidr", http.StatusUnprocessableEntity)
		return
	}
	binding := deviceBinding{ID: input.BindingID, TenantID: input.TenantID, SerialNumber: input.SerialNumber,
		Timezone: input.Timezone, SourceCIDR: normalizeCIDR(input.SourceCIDR), OwnershipVersion: input.OwnershipVersion,
		Capabilities: input.Capabilities, Active: true}
	if err := g.store.putBinding(binding); err != nil {
		http.Error(w, "failed to persist binding", http.StatusServiceUnavailable)
		return
	}
	if err := g.adms.RegisterDevice(input.SerialNumber, zkadms.WithDeviceTimezone(loc)); err != nil {
		http.Error(w, err.Error(), http.StatusConflict)
		return
	}
	w.WriteHeader(http.StatusNoContent)
}

func (g *gateway) queueCommand(w http.ResponseWriter, r *http.Request) {
	var input commandRequest
	if decodeJSON(r, &input) != nil || input.BindingID == "" || input.CommandID == "" || input.SerialNumber == "" || strings.TrimSpace(input.Command) == "" {
		http.Error(w, "invalid command", http.StatusBadRequest)
		return
	}
	if existing, found, _ := g.store.command(input.CommandID); found {
		writeJSON(w, http.StatusAccepted, existing)
		return
	}
	binding, found, err := g.store.binding(input.SerialNumber)
	if err != nil || !found || !binding.Active || binding.ID != input.BindingID || binding.TenantID != input.TenantID {
		http.Error(w, "device binding mismatch", http.StatusConflict)
		return
	}
	command := durableCommand{ID: input.CommandID, BindingID: binding.ID, TenantID: binding.TenantID,
		TenantDeviceID: input.TenantDeviceID, TenantCommandID: input.TenantCommandID, SerialNumber: input.SerialNumber,
		Command: input.Command, State: "requested", CreatedAt: time.Now().UTC()}
	if err := g.store.putCommand(command); err != nil {
		http.Error(w, "failed to persist command", http.StatusServiceUnavailable)
		return
	}
	protocolID, err := g.adms.QueueCommand(input.SerialNumber, input.Command)
	if err != nil {
		http.Error(w, err.Error(), http.StatusConflict)
		return
	}
	if err := g.store.assignProtocolCommand(command.ID, command.SerialNumber, protocolID); err != nil {
		http.Error(w, "failed to persist protocol command", http.StatusServiceUnavailable)
		return
	}
	command, _, _ = g.store.command(command.ID)
	writeJSON(w, http.StatusAccepted, command)
}

func (g *gateway) restore() error {
	bindings, err := g.store.bindings()
	if err != nil {
		return err
	}
	for _, binding := range bindings {
		if !binding.Active {
			continue
		}
		loc, err := time.LoadLocation(binding.Timezone)
		if err != nil {
			return fmt.Errorf("restore device %s timezone: %w", binding.SerialNumber, err)
		}
		if err := g.adms.RegisterDevice(binding.SerialNumber, zkadms.WithDeviceTimezone(loc)); err != nil {
			return err
		}
	}
	commands, err := g.store.recoverableCommands()
	if err != nil {
		return err
	}
	for _, command := range commands {
		if command.State != "requested" {
			_ = g.store.markCommandUnknown(command.ID)
			continue
		}
		protocolID, err := g.adms.QueueCommand(command.SerialNumber, command.Command)
		if err != nil {
			return err
		}
		if err := g.store.assignProtocolCommand(command.ID, command.SerialNumber, protocolID); err != nil {
			return err
		}
	}
	return nil
}

func (g *gateway) deliverReceipts(ctx context.Context) {
	ticker := time.NewTicker(time.Second)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			receipts, err := g.store.pendingReceipts(100)
			if err != nil {
				g.logger.Error("load receipt outbox", "error", err)
				continue
			}
			for _, receipt := range receipts {
				if err := g.config.laravel.deliver(ctx, receipt); err != nil {
					_ = g.store.markReceiptRetry(receipt.ID, err)
					continue
				}
				_ = g.store.markReceiptDelivered(receipt.ID)
			}
		}
	}
}

func (g *gateway) onAttendance(_ context.Context, record zkadms.AttendanceRecord) {
	g.appendToActive(record.SerialNumber, func(receipt *gatewayReceipt) {
		var workCode *int
		if value, err := strconv.Atoi(record.WorkCode); err == nil {
			workCode = &value
		}
		receipt.Events = append(receipt.Events, attendanceEvent{EventID: newUUID(), ReceiptID: receipt.ID,
			BindingVersion: receipt.BindingVersion, SourceLine: len(receipt.Events) + 1, PayloadDigest: receipt.PayloadDigest,
			PIN: record.UserID, OccurredAt: record.Timestamp.UTC().Format(time.RFC3339),
			OriginalTime: record.Timestamp.Format("2006-01-02 15:04:05"), PunchCode: record.Status,
			VerificationCode: record.VerifyMode, WorkCode: workCode})
	})
}

func (g *gateway) onQueryUsers(_ context.Context, serial string, users []zkadms.UserRecord) {
	g.appendToActive(serial, func(receipt *gatewayReceipt) {
		for _, user := range users {
			receipt.Users = append(receipt.Users, deviceUserEvent{PIN: user.PIN, Name: user.Name, Privilege: user.Privilege, Card: user.Card})
		}
	})
}

func (g *gateway) onDeviceInfo(_ context.Context, serial string, info map[string]string) {
	g.appendToActive(serial, func(receipt *gatewayReceipt) {
		if receipt.DeviceInfo == nil {
			receipt.DeviceInfo = map[string]string{}
		}
		for key, value := range info {
			receipt.DeviceInfo[key] = value
		}
	})
}

func (g *gateway) onCommandResult(_ context.Context, result zkadms.CommandResult) {
	code := result.ReturnCode
	state := "failed"
	if code == 0 {
		state = "acknowledged"
	}
	command, err := g.store.updateCommandByProtocol(result.SerialNumber, result.ID, func(command *durableCommand) {
		now := time.Now().UTC()
		command.State, command.ResultCode, command.CompletedAt = state, &code, &now
	})
	if err != nil {
		g.logger.Warn("unmatched command result", "serial", result.SerialNumber, "protocol_command_id", result.ID)
		return
	}
	g.appendToActive(result.SerialNumber, func(receipt *gatewayReceipt) {
		receipt.CommandUpdates = append(receipt.CommandUpdates, commandUpdate{CommandID: command.ID,
			ProtocolCommandID: result.ID, State: state, ResultCode: &code, Response: result.Command})
	})
}

func (g *gateway) appendToActive(serial string, update func(*gatewayReceipt)) {
	value, ok := g.active.Load(serial)
	if !ok {
		g.logger.Warn("callback has no active durable receipt", "serial", serial)
		return
	}
	active := value.(*activeReceipt)
	if err := g.store.updateReceipt(active.id, func(receipt *gatewayReceipt) error { update(receipt); return nil }); err != nil {
		g.logger.Error("persist protocol callback", "receipt_id", active.id, "error", err)
		return
	}
	active.complete()
}

func (g *gateway) markDeliveredCommands(serial, receiptID, body string) {
	for _, line := range strings.Split(body, "\n") {
		parts := strings.SplitN(strings.TrimSpace(line), ":", 3)
		if len(parts) != 3 || parts[0] != "C" {
			continue
		}
		protocolID, err := strconv.ParseInt(parts[1], 10, 64)
		if err != nil {
			continue
		}
		command, err := g.store.updateCommandByProtocol(serial, protocolID, func(command *durableCommand) {
			now := time.Now().UTC()
			command.State, command.DeliveredAt = "delivered", &now
		})
		if err == nil {
			_ = g.store.updateReceipt(receiptID, func(receipt *gatewayReceipt) error {
				receipt.CommandUpdates = append(receipt.CommandUpdates, commandUpdate{CommandID: command.ID, ProtocolCommandID: protocolID, State: "delivered"})
				return nil
			})
		}
	}
}

func (g *gateway) deviceLock(serial string) *sync.Mutex {
	value, _ := g.locks.LoadOrStore(serial, &sync.Mutex{})
	return value.(*sync.Mutex)
}

func canonicalADMSPath(path string) string {
	switch path {
	case "/iclock/cdata.aspx":
		return "/iclock/cdata"
	case "/iclock/getrequest.aspx":
		return "/iclock/getrequest"
	case "/iclock/devicecmd.aspx":
		return "/iclock/devicecmd"
	case "/iclock/registry.aspx":
		return "/iclock/registry"
	default:
		return path
	}
}

func (g *gateway) sourceIP(r *http.Request) (string, error) {
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		host = r.RemoteAddr
	}
	peer := net.ParseIP(host)
	if peer == nil {
		return "", errors.New("invalid remote address")
	}
	if netInAny(peer, g.config.trustedProxyNets) {
		forwarded := strings.TrimSpace(strings.Split(r.Header.Get("X-Forwarded-For"), ",")[0])
		if cloudflare := strings.TrimSpace(r.Header.Get("CF-Connecting-IP")); cloudflare != "" {
			forwarded = cloudflare
		}
		if ip := net.ParseIP(forwarded); ip != nil {
			return ip.String(), nil
		}
	}
	return peer.String(), nil
}

func (a *activeReceipt) setExpected(expected int) {
	a.mu.Lock()
	defer a.mu.Unlock()
	a.expected = expected
	if a.completed >= expected {
		a.close()
	}
}

func (a *activeReceipt) complete() {
	a.mu.Lock()
	defer a.mu.Unlock()
	a.completed++
	if a.expected >= 0 && a.completed >= a.expected {
		a.close()
	}
}

func (a *activeReceipt) close() {
	if !a.closed {
		close(a.done)
		a.closed = true
	}
}

func callbackCount(r *http.Request, body []byte, response string) int {
	if r.Method != http.MethodPost || len(body) == 0 {
		return 0
	}
	switch {
	case strings.HasSuffix(r.URL.Path, "/devicecmd"):
		return max(1, strings.Count(string(body), "ID="))
	case strings.HasSuffix(r.URL.Path, "/registry"):
		return 1
	case r.URL.Query().Get("table") == "ATTLOG":
		var count int
		_, _ = fmt.Sscanf(response, "OK: %d", &count)
		return count
	case r.URL.Query().Get("table") == "USERINFO":
		return 1
	case strings.HasSuffix(r.URL.Path, "/cdata"):
		return 1
	default:
		return 0
	}
}

func secureBearer(header, token string) bool {
	actual := strings.TrimPrefix(header, "Bearer ")
	return len(actual) == len(token) && subtle.ConstantTimeCompare([]byte(actual), []byte(token)) == 1
}

func ipAllowed(ipValue, cidr string) bool {
	cidr = strings.TrimSpace(cidr)
	if cidr == "" || cidr == "auto" || cidr == "*" || cidr == "0.0.0.0/0" {
		return true
	}
	ip := net.ParseIP(ipValue)
	_, network, err := net.ParseCIDR(normalizeCIDR(cidr))
	return err == nil && ip != nil && network.Contains(ip)
}

func normalizeCIDR(value string) string {
	value = strings.TrimSpace(value)
	if strings.Contains(value, "/") {
		return value
	}
	if ip := net.ParseIP(value); ip != nil && ip.To4() != nil {
		return value + "/32"
	}
	return value + "/128"
}

func parseCIDRs(value string) []*net.IPNet {
	var networks []*net.IPNet
	for _, item := range strings.Split(value, ",") {
		if strings.TrimSpace(item) == "" {
			continue
		}
		_, network, err := net.ParseCIDR(normalizeCIDR(item))
		if err != nil {
			panic("invalid ADMS_TRUSTED_PROXY_CIDRS: " + item)
		}
		networks = append(networks, network)
	}
	return networks
}

func netInAny(ip net.IP, networks []*net.IPNet) bool {
	for _, network := range networks {
		if network.Contains(ip) {
			return true
		}
	}
	return false
}

func decodeJSON(r *http.Request, target any) error {
	decoder := json.NewDecoder(io.LimitReader(r.Body, 1<<20))
	decoder.DisallowUnknownFields()
	return decoder.Decode(target)
}

func requiredEnv(key string) string {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		panic(key + " is required")
	}
	return value
}

func envOr(key, fallback string) string {
	if value := strings.TrimSpace(os.Getenv(key)); value != "" {
		return value
	}
	return fallback
}

func writeJSON(w http.ResponseWriter, status int, value any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(value)
}

func copyResponse(w http.ResponseWriter, status int, headers http.Header, body []byte) {
	for key, values := range headers {
		for _, value := range values {
			w.Header().Add(key, value)
		}
	}
	w.WriteHeader(status)
	_, _ = w.Write(body)
}

func writeStoredResponse(w http.ResponseWriter, receipt gatewayReceipt) {
	status := receipt.ResponseStatus
	if status == 0 {
		status = http.StatusOK
	}
	copyResponse(w, status, nil, receipt.ResponseBody)
}
