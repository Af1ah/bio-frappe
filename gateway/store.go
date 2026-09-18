package main

import (
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"net/url"
	"sort"
	"strings"
	"time"

	bolt "go.etcd.io/bbolt"
)

var (
	bucketBindings         = []byte("bindings")
	bucketReceipts         = []byte("receipts")
	bucketReceiptDigests   = []byte("receipt_digests")
	bucketCommands         = []byte("commands")
	bucketProtocolCommands = []byte("protocol_commands")
)

type durableStore struct{ db *bolt.DB }

func openDurableStore(path string) (*durableStore, error) {
	db, err := bolt.Open(path, 0o600, &bolt.Options{Timeout: 3 * time.Second})
	if err != nil {
		return nil, fmt.Errorf("open gateway store: %w", err)
	}
	store := &durableStore{db: db}
	err = db.Update(func(tx *bolt.Tx) error {
		for _, name := range [][]byte{bucketBindings, bucketReceipts, bucketReceiptDigests, bucketCommands, bucketProtocolCommands} {
			if _, err := tx.CreateBucketIfNotExists(name); err != nil {
				return err
			}
		}
		return nil
	})
	if err != nil {
		_ = db.Close()
		return nil, fmt.Errorf("initialize gateway store: %w", err)
	}
	return store, nil
}

func (s *durableStore) Close() error { return s.db.Close() }

func (s *durableStore) ready() error {
	return s.db.View(func(*bolt.Tx) error { return nil })
}

func (s *durableStore) putBinding(binding deviceBinding) error {
	return s.db.Update(func(tx *bolt.Tx) error {
		return putJSON(tx.Bucket(bucketBindings), []byte(binding.SerialNumber), binding)
	})
}

func (s *durableStore) binding(serial string) (deviceBinding, bool, error) {
	var binding deviceBinding
	err := s.db.View(func(tx *bolt.Tx) error {
		value := tx.Bucket(bucketBindings).Get([]byte(serial))
		if value == nil {
			return nil
		}
		return json.Unmarshal(value, &binding)
	})
	return binding, binding.ID != "", err
}

func (s *durableStore) bindings() ([]deviceBinding, error) {
	bindings := make([]deviceBinding, 0)
	err := s.db.View(func(tx *bolt.Tx) error {
		return tx.Bucket(bucketBindings).ForEach(func(_, value []byte) error {
			var binding deviceBinding
			if err := json.Unmarshal(value, &binding); err != nil {
				return err
			}
			bindings = append(bindings, binding)
			return nil
		})
	})
	return bindings, err
}

func (s *durableStore) createReceipt(binding deviceBinding, sourceIP, method, path string, query url.Values, body []byte) (gatewayReceipt, bool, error) {
	queryValues := make(map[string]string, len(query))
	queryKeys := make([]string, 0, len(query))
	for key := range query {
		queryKeys = append(queryKeys, key)
	}
	sort.Strings(queryKeys)
	var canonical strings.Builder
	canonical.WriteString(method)
	canonical.WriteByte('\n')
	canonical.WriteString(path)
	canonical.WriteByte('\n')
	for _, key := range queryKeys {
		value := query.Get(key)
		queryValues[key] = value
		canonical.WriteString(key)
		canonical.WriteByte('=')
		canonical.WriteString(value)
		canonical.WriteByte('&')
	}
	canonical.Write(body)
	digestBytes := sha256.Sum256([]byte(canonical.String()))
	digest := hex.EncodeToString(digestBytes[:])

	// Polling GETs are separate transport receipts even when their URL is identical.
	deduplicate := method != "GET"
	indexKey := []byte(binding.ID + ":" + digest)
	receipt := gatewayReceipt{}
	existed := false
	err := s.db.Update(func(tx *bolt.Tx) error {
		if deduplicate {
			if existingID := tx.Bucket(bucketReceiptDigests).Get(indexKey); existingID != nil {
				value := tx.Bucket(bucketReceipts).Get(existingID)
				if value == nil {
					return errors.New("receipt index points to a missing receipt")
				}
				existed = true
				return json.Unmarshal(value, &receipt)
			}
		}

		receipt = gatewayReceipt{
			ID:             newUUID(),
			BindingID:      binding.ID,
			BindingVersion: binding.OwnershipVersion,
			SerialNumber:   binding.SerialNumber,
			SourceIP:       sourceIP,
			Method:         method,
			Path:           path,
			Query:          queryValues,
			Payload:        append([]byte(nil), body...),
			PayloadDigest:  digest,
			ReceivedAt:     time.Now().UTC(),
			Status:         "received",
		}
		if err := putJSON(tx.Bucket(bucketReceipts), []byte(receipt.ID), receipt); err != nil {
			return err
		}
		if deduplicate {
			return tx.Bucket(bucketReceiptDigests).Put(indexKey, []byte(receipt.ID))
		}
		return nil
	})
	return receipt, existed, err
}

func (s *durableStore) updateReceipt(id string, update func(*gatewayReceipt) error) error {
	return s.db.Update(func(tx *bolt.Tx) error {
		bucket := tx.Bucket(bucketReceipts)
		value := bucket.Get([]byte(id))
		if value == nil {
			return fmt.Errorf("receipt %s not found", id)
		}
		var receipt gatewayReceipt
		if err := json.Unmarshal(value, &receipt); err != nil {
			return err
		}
		if err := update(&receipt); err != nil {
			return err
		}
		return putJSON(bucket, []byte(id), receipt)
	})
}

func (s *durableStore) receipt(id string) (gatewayReceipt, error) {
	var receipt gatewayReceipt
	err := s.db.View(func(tx *bolt.Tx) error {
		value := tx.Bucket(bucketReceipts).Get([]byte(id))
		if value == nil {
			return fmt.Errorf("receipt %s not found", id)
		}
		return json.Unmarshal(value, &receipt)
	})
	return receipt, err
}

func (s *durableStore) pendingReceipts(limit int) ([]gatewayReceipt, error) {
	now := time.Now().UTC()
	receipts := make([]gatewayReceipt, 0, limit)
	err := s.db.View(func(tx *bolt.Tx) error {
		return tx.Bucket(bucketReceipts).ForEach(func(_, value []byte) error {
			if len(receipts) >= limit {
				return nil
			}
			var receipt gatewayReceipt
			if err := json.Unmarshal(value, &receipt); err != nil {
				return err
			}
			if receipt.Status == "processed" && (receipt.NextAttemptAt.IsZero() || !receipt.NextAttemptAt.After(now)) {
				receipts = append(receipts, receipt)
			}
			return nil
		})
	})
	sort.Slice(receipts, func(i, j int) bool { return receipts[i].ReceivedAt.Before(receipts[j].ReceivedAt) })
	return receipts, err
}

func (s *durableStore) markReceiptDelivered(id string) error {
	now := time.Now().UTC()
	return s.updateReceipt(id, func(receipt *gatewayReceipt) error {
		receipt.Status = "delivered"
		receipt.DeliveredAt = &now
		receipt.LastError = ""
		return nil
	})
}

func (s *durableStore) markReceiptRetry(id string, cause error) error {
	return s.updateReceipt(id, func(receipt *gatewayReceipt) error {
		receipt.Attempts++
		delay := time.Second * time.Duration(1<<min(receipt.Attempts, 8))
		receipt.NextAttemptAt = time.Now().UTC().Add(delay)
		receipt.LastError = cause.Error()
		return nil
	})
}

func (s *durableStore) putCommand(command durableCommand) error {
	return s.db.Update(func(tx *bolt.Tx) error {
		return putJSON(tx.Bucket(bucketCommands), []byte(command.ID), command)
	})
}

func (s *durableStore) command(id string) (durableCommand, bool, error) {
	var command durableCommand
	err := s.db.View(func(tx *bolt.Tx) error {
		value := tx.Bucket(bucketCommands).Get([]byte(id))
		if value == nil {
			return nil
		}
		return json.Unmarshal(value, &command)
	})
	return command, command.ID != "", err
}

func (s *durableStore) assignProtocolCommand(id, serial string, protocolID int64) error {
	return s.db.Update(func(tx *bolt.Tx) error {
		commands := tx.Bucket(bucketCommands)
		value := commands.Get([]byte(id))
		if value == nil {
			return fmt.Errorf("command %s not found", id)
		}
		var command durableCommand
		if err := json.Unmarshal(value, &command); err != nil {
			return err
		}
		if command.ProtocolCommandID != 0 {
			_ = tx.Bucket(bucketProtocolCommands).Delete(protocolCommandKey(serial, command.ProtocolCommandID))
		}
		command.ProtocolCommandID = protocolID
		command.State = "queued"
		if err := putJSON(commands, []byte(id), command); err != nil {
			return err
		}
		return tx.Bucket(bucketProtocolCommands).Put(protocolCommandKey(serial, protocolID), []byte(id))
	})
}

func (s *durableStore) updateCommandByProtocol(serial string, protocolID int64, update func(*durableCommand)) (durableCommand, error) {
	var command durableCommand
	err := s.db.Update(func(tx *bolt.Tx) error {
		id := tx.Bucket(bucketProtocolCommands).Get(protocolCommandKey(serial, protocolID))
		if id == nil {
			return fmt.Errorf("protocol command %s:%d not found", serial, protocolID)
		}
		value := tx.Bucket(bucketCommands).Get(id)
		if value == nil {
			return fmt.Errorf("command %s not found", string(id))
		}
		if err := json.Unmarshal(value, &command); err != nil {
			return err
		}
		update(&command)
		return putJSON(tx.Bucket(bucketCommands), id, command)
	})
	return command, err
}

func (s *durableStore) recoverableCommands() ([]durableCommand, error) {
	commands := make([]durableCommand, 0)
	err := s.db.View(func(tx *bolt.Tx) error {
		return tx.Bucket(bucketCommands).ForEach(func(_, value []byte) error {
			var command durableCommand
			if err := json.Unmarshal(value, &command); err != nil {
				return err
			}
			if command.State == "requested" || command.State == "queued" || command.State == "delivered" {
				commands = append(commands, command)
			}
			return nil
		})
	})
	return commands, err
}

func (s *durableStore) markCommandUnknown(id string) error {
	return s.db.Update(func(tx *bolt.Tx) error {
		bucket := tx.Bucket(bucketCommands)
		value := bucket.Get([]byte(id))
		if value == nil {
			return fmt.Errorf("command %s not found", id)
		}
		var command durableCommand
		if err := json.Unmarshal(value, &command); err != nil {
			return err
		}
		command.State = "outcome_unknown"
		return putJSON(bucket, []byte(id), command)
	})
}

func protocolCommandKey(serial string, id int64) []byte {
	return []byte(fmt.Sprintf("%s:%d", serial, id))
}

func putJSON(bucket *bolt.Bucket, key []byte, value any) error {
	encoded, err := json.Marshal(value)
	if err != nil {
		return err
	}
	return bucket.Put(key, encoded)
}

func newUUID() string {
	value := make([]byte, 16)
	if _, err := rand.Read(value); err != nil {
		panic(err)
	}
	value[6] = (value[6] & 0x0f) | 0x40
	value[8] = (value[8] & 0x3f) | 0x80
	return fmt.Sprintf("%08x-%04x-%04x-%04x-%012x",
		value[0:4], value[4:6], value[6:8], value[8:10], value[10:16])
}
