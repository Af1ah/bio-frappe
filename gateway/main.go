// The ADMS protocol is implemented by the pinned upstream library. This file
// only exposes a private, token-authenticated management boundary for Laravel.
package main

import (
	"context"
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"strings"
	"time"

	zkadms "github.com/s0x90/zkteco-adms"
)

type commandRequest struct {
	SerialNumber string `json:"serial_number"`
	Command      string `json:"command"`
	CommandID    string `json:"command_id"`
}
type deviceRequest struct {
	SerialNumber string `json:"serial_number"`
	Timezone     string `json:"timezone"`
}
type gateway struct {
	adms  *zkadms.ADMSServer
	token string
}

func (g gateway) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method == http.MethodGet && (r.URL.Path == "/" || r.URL.Path == "/health") {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]string{
			"status":          "ok",
			"service":         "BIO-Notifier standalone ADMS",
			"device_endpoint": "/iclock/cdata",
		})
		return
	}
	if strings.HasPrefix(r.URL.Path, "/internal/") {
		g.manage(w, r)
		return
	}
	if !strings.HasPrefix(r.URL.Path, "/iclock/") {
		http.NotFound(w, r)
		return
	}
	g.adms.ServeHTTP(w, r)
}

func (g gateway) manage(w http.ResponseWriter, r *http.Request) {
	if r.Header.Get("Authorization") != "Bearer "+g.token {
		http.Error(w, "unauthorized", http.StatusUnauthorized)
		return
	}
	switch {
	case r.Method == http.MethodGet && r.URL.Path == "/internal/v1/health/ready":
		w.WriteHeader(http.StatusNoContent)
	case r.Method == http.MethodPost && r.URL.Path == "/internal/v1/devices":
		var input deviceRequest
		if json.NewDecoder(r.Body).Decode(&input) != nil || input.SerialNumber == "" {
			http.Error(w, "invalid device", http.StatusBadRequest)
			return
		}
		loc, err := time.LoadLocation(input.Timezone)
		if err != nil {
			http.Error(w, "invalid timezone", http.StatusUnprocessableEntity)
			return
		}
		if err := g.adms.RegisterDevice(input.SerialNumber, zkadms.WithDeviceTimezone(loc)); err != nil {
			http.Error(w, err.Error(), http.StatusConflict)
			return
		}
		w.WriteHeader(http.StatusNoContent)
	case r.Method == http.MethodPost && r.URL.Path == "/internal/v1/commands":
		var input commandRequest
		if json.NewDecoder(r.Body).Decode(&input) != nil || input.SerialNumber == "" || input.Command == "" {
			http.Error(w, "invalid command", http.StatusBadRequest)
			return
		}
		id, err := g.adms.QueueCommand(input.SerialNumber, input.Command)
		if err != nil {
			http.Error(w, err.Error(), http.StatusConflict)
			return
		}
		_ = json.NewEncoder(w).Encode(map[string]any{"protocol_command_id": id, "command_id": input.CommandID})
	default:
		http.NotFound(w, r)
	}
}

func main() {
	token := os.Getenv("ADMS_MANAGEMENT_TOKEN")
	if token == "" {
		panic("ADMS_MANAGEMENT_TOKEN is required")
	}
	logger := slog.New(slog.NewJSONHandler(os.Stderr, nil))
	adms := zkadms.NewADMSServer(
		zkadms.WithLogger(logger), zkadms.WithMaxBodySize(5<<20), zkadms.WithMaxCommandsPerDevice(25),
		zkadms.WithOnAttendance(func(_ context.Context, record zkadms.AttendanceRecord) {
			logger.Info("attendance received", "device", record.SerialNumber, "pin", record.UserID)
		}),
	)
	defer adms.Close()
	server := &http.Server{Addr: ":8080", Handler: gateway{adms: adms, token: token}, ReadHeaderTimeout: 5 * time.Second, ReadTimeout: 15 * time.Second, WriteTimeout: 15 * time.Second, IdleTimeout: 60 * time.Second}
	if err := server.ListenAndServe(); !errors.Is(err, http.ErrServerClosed) {
		panic(err)
	}
}
