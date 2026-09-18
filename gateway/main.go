package main

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"syscall"
	"time"

	zkadms "github.com/s0x90/zkteco-adms"
)

func main() {
	config := loadConfig()
	logger := slog.New(slog.NewJSONHandler(os.Stderr, nil))
	if err := os.MkdirAll(filepath.Dir(config.storePath), 0o750); err != nil {
		panic(err)
	}
	store, err := openDurableStore(config.storePath)
	if err != nil {
		panic(err)
	}
	defer store.Close()

	ctx, cancel := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer cancel()
	g := newGateway(config, store, logger)
	adms := zkadms.NewADMSServer(
		zkadms.WithBaseContext(ctx), zkadms.WithLogger(logger), zkadms.WithMaxBodySize(5<<20),
		zkadms.WithMaxCommandsPerDevice(100), zkadms.WithOnAttendance(g.onAttendance),
		zkadms.WithOnDeviceInfo(g.onDeviceInfo), zkadms.WithOnRegistry(g.onDeviceInfo),
		zkadms.WithOnCommandResult(g.onCommandResult), zkadms.WithOnQueryUsers(g.onQueryUsers),
	)
	g.adms = adms
	defer adms.Close()
	if err := g.restore(); err != nil {
		panic(err)
	}
	go g.deliverReceipts(ctx)

	deviceServer := httpServer(config.deviceAddr, http.HandlerFunc(g.serveDevice))
	managementServer := httpServer(config.managementAddr, http.HandlerFunc(g.serveManagement))
	serverErrors := make(chan error, 2)
	go func() { serverErrors <- deviceServer.ListenAndServe() }()
	go func() { serverErrors <- managementServer.ListenAndServe() }()
	select {
	case <-ctx.Done():
	case err := <-serverErrors:
		if !errors.Is(err, http.ErrServerClosed) {
			logger.Error("gateway server stopped", "error", err)
		}
	}
	cancel()
	shutdownCtx, shutdownCancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer shutdownCancel()
	_ = deviceServer.Shutdown(shutdownCtx)
	_ = managementServer.Shutdown(shutdownCtx)
}

func httpServer(addr string, handler http.Handler) *http.Server {
	return &http.Server{Addr: addr, Handler: handler, ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout: 20 * time.Second, WriteTimeout: 20 * time.Second, IdleTimeout: 60 * time.Second}
}
