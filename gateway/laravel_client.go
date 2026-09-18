package main

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

type laravelClient struct {
	baseURL string
	token   string
	client  *http.Client
}

func newLaravelClient(baseURL, token string) laravelClient {
	return laravelClient{baseURL: baseURL, token: token, client: &http.Client{Timeout: 15 * time.Second}}
}

func (c laravelClient) deliver(ctx context.Context, receipt gatewayReceipt) error {
	eventChunks := chunk(receipt.Events, 250)
	userChunks := chunk(receipt.Users, 1000)
	requests := max(len(eventChunks), len(userChunks), 1)
	for index := 0; index < requests; index++ {
		var events []attendanceEvent
		var users []deviceUserEvent
		if index < len(eventChunks) {
			events = eventChunks[index]
		}
		if index < len(userChunks) {
			users = userChunks[index]
		}
		updates := []commandUpdate(nil)
		info := map[string]string(nil)
		if index == 0 {
			updates, info = receipt.CommandUpdates, receipt.DeviceInfo
		}
		if err := c.deliverBatch(ctx, receipt, events, users, updates, info); err != nil {
			return err
		}
	}
	return nil
}

func (c laravelClient) deliverBatch(ctx context.Context, receipt gatewayReceipt, events []attendanceEvent, users []deviceUserEvent, updates []commandUpdate, info map[string]string) error {
	if events == nil {
		events = []attendanceEvent{}
	}
	if users == nil {
		users = []deviceUserEvent{}
	}
	if updates == nil {
		updates = []commandUpdate{}
	}
	if info == nil {
		info = map[string]string{}
	}
	payload := map[string]any{
		"receipt": map[string]any{
			"id": receipt.ID, "device_binding_id": receipt.BindingID,
			"binding_version": receipt.BindingVersion, "serial_number": receipt.SerialNumber,
			"source_ip": receipt.SourceIP, "endpoint": receipt.Path, "method": receipt.Method,
			"query": receipt.Query, "payload": receipt.Payload, "payload_digest": receipt.PayloadDigest,
			"received_at": receipt.ReceivedAt,
		},
		"events": events, "users": users,
		"command_updates": updates, "device_info": info,
	}
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost,
		strings.TrimRight(c.baseURL, "/")+"/api/internal/v1/device-events", bytes.NewReader(body))
	if err != nil {
		return err
	}
	req.Header.Set("Authorization", "Bearer "+c.token)
	req.Header.Set("Content-Type", "application/json")
	response, err := c.client.Do(req)
	if err != nil {
		return err
	}
	defer response.Body.Close()
	if response.StatusCode < 200 || response.StatusCode >= 300 {
		message, _ := io.ReadAll(io.LimitReader(response.Body, 2048))
		return fmt.Errorf("laravel returned %d: %s", response.StatusCode, strings.TrimSpace(string(message)))
	}
	return nil
}

func chunk[T any](values []T, size int) [][]T {
	if len(values) == 0 {
		return nil
	}
	chunks := make([][]T, 0, (len(values)+size-1)/size)
	for start := 0; start < len(values); start += size {
		end := min(start+size, len(values))
		chunks = append(chunks, values[start:end])
	}
	return chunks
}
