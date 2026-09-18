package main

import (
	"net/url"
	"path/filepath"
	"testing"
)

func TestReceiptDeduplicationIsLimitedToDevicePosts(t *testing.T) {
	store, err := openDurableStore(filepath.Join(t.TempDir(), "gateway.db"))
	if err != nil {
		t.Fatal(err)
	}
	defer store.Close()
	binding := deviceBinding{ID: newUUID(), SerialNumber: "TEST001", OwnershipVersion: 1, Active: true}

	first, existed, err := store.createReceipt(binding, "192.0.2.10", "POST", "/iclock/cdata", url.Values{"SN": {"TEST001"}}, []byte("payload"))
	if err != nil || existed {
		t.Fatalf("first receipt: existed=%v err=%v", existed, err)
	}
	second, existed, err := store.createReceipt(binding, "192.0.2.10", "POST", "/iclock/cdata", url.Values{"SN": {"TEST001"}}, []byte("payload"))
	if err != nil || !existed || second.ID != first.ID {
		t.Fatalf("duplicate receipt was not reused: existed=%v err=%v", existed, err)
	}
	getOne, _, _ := store.createReceipt(binding, "192.0.2.10", "GET", "/iclock/getrequest", url.Values{"SN": {"TEST001"}}, nil)
	getTwo, _, _ := store.createReceipt(binding, "192.0.2.10", "GET", "/iclock/getrequest", url.Values{"SN": {"TEST001"}}, nil)
	if getOne.ID == getTwo.ID {
		t.Fatal("polling GET receipts must remain distinct")
	}
}

func TestNewUUIDHasCanonicalShape(t *testing.T) {
	value := newUUID()
	if len(value) != 36 || value[8] != '-' || value[13] != '-' || value[18] != '-' || value[23] != '-' {
		t.Fatalf("invalid UUID: %q", value)
	}
}
