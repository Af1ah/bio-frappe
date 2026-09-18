package main

import "time"

type deviceBinding struct {
	ID               string            `json:"id"`
	TenantID         string            `json:"tenant_id"`
	SerialNumber     string            `json:"serial_number"`
	Timezone         string            `json:"timezone"`
	SourceCIDR       string            `json:"source_cidr"`
	OwnershipVersion int               `json:"ownership_version"`
	Capabilities     map[string]string `json:"capabilities,omitempty"`
	Active           bool              `json:"active"`
}

type attendanceEvent struct {
	EventID          string `json:"event_id"`
	ReceiptID        string `json:"receipt_id"`
	BindingVersion   int    `json:"binding_version"`
	SourceLine       int    `json:"source_line"`
	PayloadDigest    string `json:"payload_digest"`
	PIN              string `json:"pin"`
	OccurredAt       string `json:"occurred_at"`
	OriginalTime     string `json:"original_timestamp"`
	PunchCode        int    `json:"punch_code"`
	VerificationCode int    `json:"verification_code"`
	WorkCode         *int   `json:"work_code,omitempty"`
	Classification   string `json:"classification,omitempty"`
}

type deviceUserEvent struct {
	PIN       string `json:"pin"`
	Name      string `json:"name"`
	Privilege int    `json:"privilege"`
	Card      string `json:"card,omitempty"`
}

type commandUpdate struct {
	CommandID         string `json:"command_id"`
	ProtocolCommandID int64  `json:"protocol_command_id"`
	State             string `json:"state"`
	ResultCode        *int   `json:"result_code,omitempty"`
	Response          string `json:"response,omitempty"`
}

type gatewayReceipt struct {
	ID             string            `json:"id"`
	BindingID      string            `json:"device_binding_id"`
	BindingVersion int               `json:"binding_version"`
	SerialNumber   string            `json:"serial_number"`
	SourceIP       string            `json:"source_ip"`
	Method         string            `json:"method"`
	Path           string            `json:"path"`
	Query          map[string]string `json:"query"`
	Payload        []byte            `json:"payload"`
	PayloadDigest  string            `json:"payload_digest"`
	ReceivedAt     time.Time         `json:"received_at"`
	Status         string            `json:"status"`
	ResponseStatus int               `json:"response_status,omitempty"`
	ResponseBody   []byte            `json:"response_body,omitempty"`
	Events         []attendanceEvent `json:"events,omitempty"`
	Users          []deviceUserEvent `json:"users,omitempty"`
	CommandUpdates []commandUpdate   `json:"command_updates,omitempty"`
	DeviceInfo     map[string]string `json:"device_info,omitempty"`
	Attempts       int               `json:"attempts"`
	NextAttemptAt  time.Time         `json:"next_attempt_at,omitempty"`
	DeliveredAt    *time.Time        `json:"delivered_at,omitempty"`
	LastError      string            `json:"last_error,omitempty"`
}

type durableCommand struct {
	ID                string     `json:"id"`
	BindingID         string     `json:"binding_id"`
	TenantID          string     `json:"tenant_id"`
	TenantDeviceID    int64      `json:"tenant_device_id"`
	TenantCommandID   int64      `json:"tenant_command_id"`
	SerialNumber      string     `json:"serial_number"`
	Command           string     `json:"command"`
	State             string     `json:"state"`
	ProtocolCommandID int64      `json:"protocol_command_id,omitempty"`
	CreatedAt         time.Time  `json:"created_at"`
	DeliveredAt       *time.Time `json:"delivered_at,omitempty"`
	CompletedAt       *time.Time `json:"completed_at,omitempty"`
	ResultCode        *int       `json:"result_code,omitempty"`
}

type commandRequest struct {
	BindingID       string `json:"binding_id"`
	CommandID       string `json:"command_id"`
	TenantID        string `json:"tenant_id"`
	TenantDeviceID  int64  `json:"tenant_device_id"`
	TenantCommandID int64  `json:"tenant_command_id"`
	SerialNumber    string `json:"serial_number"`
	Command         string `json:"command"`
}

type deviceRequest struct {
	BindingID        string            `json:"binding_id"`
	TenantID         string            `json:"tenant_id"`
	SerialNumber     string            `json:"serial_number"`
	Timezone         string            `json:"timezone"`
	SourceCIDR       string            `json:"source_cidr"`
	OwnershipVersion int               `json:"ownership_version"`
	Capabilities     map[string]string `json:"capabilities,omitempty"`
}
