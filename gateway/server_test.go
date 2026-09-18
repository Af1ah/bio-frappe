package main

import (
	"context"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"path/filepath"
	"strings"
	"testing"

	zkadms "github.com/s0x90/zkteco-adms"
)

func TestUnknownAndMismatchedDeviceSourcesAreRejectedBeforeProtocolHandling(t *testing.T) {
	g, closeGateway := testGateway(t)
	defer closeGateway()

	unknown := httptest.NewRequest(http.MethodGet, "/iclock/getrequest?SN=UNKNOWN", nil)
	unknown.RemoteAddr = "192.0.2.10:5000"
	unknownResponse := httptest.NewRecorder()
	g.serveDevice(unknownResponse, unknown)
	if unknownResponse.Code != http.StatusForbidden {
		t.Fatalf("unknown device status=%d", unknownResponse.Code)
	}

	binding := deviceBinding{ID: newUUID(), TenantID: "tenant", SerialNumber: "TEST001", Timezone: "UTC", SourceCIDR: "192.0.2.10/32", OwnershipVersion: 1, Active: true}
	if err := g.store.putBinding(binding); err != nil {
		t.Fatal(err)
	}
	if err := g.adms.RegisterDevice(binding.SerialNumber); err != nil {
		t.Fatal(err)
	}
	mismatch := httptest.NewRequest(http.MethodGet, "/iclock/getrequest?SN=TEST001", nil)
	mismatch.RemoteAddr = "192.0.2.11:5000"
	mismatchResponse := httptest.NewRecorder()
	g.serveDevice(mismatchResponse, mismatch)
	if mismatchResponse.Code != http.StatusForbidden {
		t.Fatalf("mismatched source status=%d", mismatchResponse.Code)
	}
}

func TestAttendanceRequestIsDurableBeforeAcknowledgement(t *testing.T) {
	g, closeGateway := testGateway(t)
	defer closeGateway()
	binding := deviceBinding{ID: newUUID(), TenantID: "tenant", SerialNumber: "TEST001", Timezone: "UTC", SourceCIDR: "192.0.2.10/32", OwnershipVersion: 1, Active: true}
	if err := g.store.putBinding(binding); err != nil {
		t.Fatal(err)
	}
	if err := g.adms.RegisterDevice(binding.SerialNumber); err != nil {
		t.Fatal(err)
	}

	request := httptest.NewRequest(http.MethodPost, "/iclock/cdata?SN=TEST001&table=ATTLOG", strings.NewReader("1001\t2026-09-17 09:00:00\t0\t1\t0"))
	request.RemoteAddr = "192.0.2.10:5000"
	response := httptest.NewRecorder()
	g.serveDevice(response, request)
	if response.Code != http.StatusOK || !strings.HasPrefix(response.Body.String(), "OK: 1") {
		t.Fatalf("unexpected response: %d %q", response.Code, response.Body.String())
	}
	receipts, err := g.store.pendingReceipts(10)
	if err != nil || len(receipts) != 1 {
		t.Fatalf("pending receipts=%d err=%v", len(receipts), err)
	}
	if receipts[0].Status != "processed" || len(receipts[0].Events) != 1 || string(receipts[0].Payload) == "" {
		t.Fatalf("receipt was not fully persisted: %#v", receipts[0])
	}
}

func TestAspxCommandPollUsesTheCanonicalADMSRoute(t *testing.T) {
	g, closeGateway := testGateway(t)
	defer closeGateway()
	binding := deviceBinding{ID: newUUID(), TenantID: "tenant", SerialNumber: "TEST001", Timezone: "UTC", SourceCIDR: "192.0.2.10/32", OwnershipVersion: 1, Active: true}
	if err := g.store.putBinding(binding); err != nil {
		t.Fatal(err)
	}
	if err := g.adms.RegisterDevice(binding.SerialNumber); err != nil {
		t.Fatal(err)
	}

	command := durableCommand{ID: newUUID(), BindingID: binding.ID, TenantID: binding.TenantID, SerialNumber: binding.SerialNumber, Command: "CHECK", State: "requested"}
	if err := g.store.putCommand(command); err != nil {
		t.Fatal(err)
	}
	protocolID, err := g.adms.QueueCommand(binding.SerialNumber, command.Command)
	if err != nil {
		t.Fatal(err)
	}
	if err := g.store.assignProtocolCommand(command.ID, binding.SerialNumber, protocolID); err != nil {
		t.Fatal(err)
	}

	request := httptest.NewRequest(http.MethodGet, "/iclock/cdata.aspx?SN=TEST001", nil)
	request.RemoteAddr = "192.0.2.10:5000"
	response := httptest.NewRecorder()
	g.serveDevice(response, request)
	if response.Code != http.StatusOK || !strings.Contains(response.Body.String(), "C:") || !strings.Contains(response.Body.String(), ":CHECK") {
		t.Fatalf("unexpected command poll response: %d %q", response.Code, response.Body.String())
	}

	stored, found, err := g.store.command(command.ID)
	if err != nil || !found || stored.State != "delivered" {
		t.Fatalf("command delivery state=%q found=%t err=%v", stored.State, found, err)
	}
}

func testGateway(t *testing.T) (*gateway, func()) {
	t.Helper()
	store, err := openDurableStore(filepath.Join(t.TempDir(), "gateway.db"))
	if err != nil {
		t.Fatal(err)
	}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	g := newGateway(gatewayConfig{managementToken: "test"}, store, logger)
	g.adms = zkadms.NewADMSServer(
		zkadms.WithBaseContext(context.Background()),
		zkadms.WithOnAttendance(g.onAttendance), zkadms.WithOnDeviceInfo(g.onDeviceInfo),
		zkadms.WithOnRegistry(g.onDeviceInfo), zkadms.WithOnCommandResult(g.onCommandResult),
		zkadms.WithOnQueryUsers(g.onQueryUsers),
	)
	return g, func() { g.adms.Close(); store.Close() }
}
