"""Matrix COSEC ACS Bluetooth Low Energy (BLE) Protocol Implementation.

Reverse-engineered from Matrix COSEC ACS 3.3.1 (com.matrixcomsec.acs) and
validated against live Matrix ARGO FACE door controller (MAC 30:30:D0:2D:B5:58).

Features:
- Master static key and GATT service / characteristic UUIDs.
- CRC16-A calculation (polynomial 0x1021, seed 0xC6C6/50886, bit-reflected).
- Ephemeral session key derivation from challenge via AES-256-CBC (zero IV).
- Proprietary framing format: [MsgNo: uint16 LE] [NumFields: uint8] [Lens...] [Payloads...].
- Access request generation for Matrix Proprietary (Msg 6), Wiegand (Msg 10), and PIN (Msg 7).
- Full response status and reason code dictionary parsing.
- Async live execution client via Bleak for automated device testing.
"""

from __future__ import annotations

import argparse
import asyncio
import struct
import sys
from typing import Any

from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes

# ---------------------------------------------------------------------------
# Cryptographic Keys and UUIDs
# ---------------------------------------------------------------------------

# Master Static AES-256 Application Key extracted from Cosec_ACS_Shared.MxConstants
MASTER_APPLICATION_KEY: bytes = b"P138Q2D6X95AWFE4CHBZ96MRGF71VUSK"

# Zero IV used across all AES-256-CBC operations in Matrix COSEC BLE
ZERO_IV: bytes = bytes(16)

# GATT Service & Characteristic UUIDs for COSEC ARGO / CB Series
CB_DOOR_SERVICE_UUID: str = "cb89eab1-79fd-4770-9b3f-8b5733da83d5"
CB_CHAR_RANDOM_KEY_APTA: str = "cb89eab2-79fd-4770-9b3f-8b5733da83d5"  # Read (APTA)
CB_CHAR_REQUEST_WRITE: str = "cb89eab3-79fd-4770-9b3f-8b5733da83d5"    # Write (Access/PIN/Face)
CB_CHAR_RESPONSE_NOTIFY: str = "cb89eab4-79fd-4770-9b3f-8b5733da83d5"  # Read / Notify (Results)
CB_CHAR_RANDOM_KEY_ACS: str = "cb89eab5-79fd-4770-9b3f-8b5733da83d5"   # Read (ACS challenge)
CB_CHAR_ENROL_READ: str = "cb89eab6-79fd-4770-9b3f-8b5733da83d5"       # Read (Enrolment)

# GATT Service & Characteristic UUIDs for VEGA Series (for reference)
VEGA_DOOR_SERVICE_UUID: str = "bce18a0d-7608-63aa-bdef-cafbca1a8907"
VEGA_CHAR_REQUEST_WRITE: str = "bce18a0d-7608-63aa-bdef-cafbca1a8908"
VEGA_CHAR_RANDOM_KEY_APTA: str = "bce18a0d-7608-63aa-bdef-cafbca1a8909"
VEGA_CHAR_RESPONSE_NOTIFY: str = "bce18a0d-7608-63aa-bdef-cafbca1a890a"
VEGA_CHAR_RANDOM_KEY_ACS: str = "bce18a0d-7608-63aa-bdef-cafbca1a890b"
VEGA_CHAR_ENROL_READ: str = "bce18a0d-7608-63aa-bdef-cafbca1a890c"

# Message IDs
MSG_ACCESS_REQUEST_APTA: int = 1
MSG_CHALLENGE_APTA: int = 2
MSG_ACCESS_RESULT: int = 3
MSG_ENROL_RESULT: int = 4
MSG_CHALLENGE_ACS: int = 5
MSG_ACCESS_REQUEST_MATRIX: int = 6
MSG_PIN_REQUEST: int = 7
MSG_FACE_REQUEST: int = 8
MSG_FACE_ACK: int = 9
MSG_ACCESS_REQUEST_WIEGAND: int = 10

# Wiegand Manual ID Formats
WIEGAND_FORMAT_26BIT: int = 1
WIEGAND_FORMAT_35BIT: int = 3
WIEGAND_FORMAT_37BIT: int = 5

# ---------------------------------------------------------------------------
# Status and Reason Code Dictionaries
# ---------------------------------------------------------------------------

MESSAGE_CODES: dict[int, str] = {
    1: "Access Allowed",
    2: "Access Denied",
    3: "Waiting for second user",
    4: "Device busy",
    5: "Waiting for second credential",
    6: "Oops!! Please try again. (Framing/CRC failure)",
    7: "Oops!! Please try again.",
    8: "Communication Error",
    10: "Communication Time-Out",
    11: "Access Request Initiated",
    12: "Card + PIN Required",
    13: "Card + Face Required",
    14: "Approach Device for Temperature Detection",
    15: "Temperature Sensor Disconnected on Device",
    16: "Wait Time",
    17: "Enable Face Recognition on Device for Mask Detection",
    18: "Approach Device for Face Mask Detection",
    19: "Application Not Compatible",
}

DENIED_REASON_CODES: dict[int, str] = {
    1: "2-Person Must Zone",
    2: "Exit Not Recorded",
    3: "Entry Not Recorded",
    4: "User is Blocked",
    6: "Event Registered",
    7: "Door is Locked",
    8: "No First User Entry",
    9: "Too Many Occupancy",
    10: "Occupancy Violated",
    11: "Occupancy Violated",
    12: "Timeout",
    13: "User Validity expired",
    14: "System error",
    15: "Visitor needs escort",
    16: "User not identified",
    17: "Entry restricted",
    18: "User not active",
    19: "Invalid input",
    21: "User not allowed",
    22: "Transaction Denied",
    23: "Threshold Temperature Exceeded",
    24: "Face Recognition is Disabled",
}

ALLOWED_REASON_CODES: dict[int, str] = {
    1: "Transaction Approved",
    21: "Pass Surrendered",
}

WAITING_REASON_CODES: dict[int, str] = {
    0: "Control Zone",
    1: "2-Person Must Zone",
}


# ---------------------------------------------------------------------------
# Framing and Parsing
# ---------------------------------------------------------------------------

def parse_message(data: bytes) -> tuple[int, list[bytes]]:
    """Parse a Matrix binary framed message into its message number and field list."""
    if len(data) < 3:
        raise ValueError("Truncated message header")
    number, count = struct.unpack_from("<HB", data)
    offset = 3 + 2 * count
    if len(data) < offset:
        raise ValueError("Truncated field lengths")
    sizes = struct.unpack_from("<" + "H" * count, data, 3)
    if offset + sum(sizes) > len(data):
        raise ValueError(
            f"Message length mismatch: expected at least {offset + sum(sizes)}, got {len(data)}"
        )
    fields = []
    for size in sizes:
        fields.append(data[offset : offset + size])
        offset += size
    return number, fields


def frame_message(number: int, fields: list[bytes]) -> bytes:
    """Frame fields into a Matrix binary message: [MsgNo: uint16 LE][Count: uint8][Lens...][Data...]."""
    return (
        struct.pack("<HB", number, len(fields))
        + b"".join(struct.pack("<H", len(f)) for f in fields)
        + b"".join(fields)
    )


# ---------------------------------------------------------------------------
# CRC16-A and Payloads
# ---------------------------------------------------------------------------

def _reflect(value: int, bits: int) -> int:
    """Reflect the bit order of an integer."""
    return int(f"{value:0{bits}b}"[::-1], 2)


def calculate_crc16_a(data: bytes) -> int:
    """Calculate the 16-bit CRC16-A checksum used by Matrix COSEC ACS."""
    crc = 50886  # 0xC6C6 seed
    for byte in data:
        crc ^= _reflect(byte, 8) << 8
        for _ in range(8):
            crc = ((crc << 1) ^ 0x1021 if crc & 0x8000 else crc << 1) & 0xFFFFFFFF
    return _reflect(crc & 0xFFFF, 16)


def access_payload(access_id: str) -> bytes:
    """Generate 32-byte access payload: [2-byte CRC16-A LE] + [30-byte ASCII '0'-padded Access ID]."""
    if not access_id.isascii() or not access_id.isdecimal() or not 1 <= len(access_id) <= 20:
        raise ValueError("Access ID must be a 1-20 digit decimal string")
    if not 0 < int(access_id) <= 18446744073709551615:
        raise ValueError("Access ID must fit a nonzero unsigned 64-bit value")
    data = access_id.zfill(30).encode("ascii")
    crc = calculate_crc16_a(data)
    return struct.pack("<H", crc) + data


def facility_code_payload(facility_code: str) -> bytes:
    """Generate 32-byte facility code payload with CRC-16A."""
    data = facility_code.zfill(30).encode("ascii")
    crc = calculate_crc16_a(data)
    return struct.pack("<H", crc) + data


def pin_payload(pin: str) -> bytes:
    """Generate 32-byte PIN payload with trailing zero padding."""
    if not pin.isascii() or not pin.isdecimal() or not 1 <= len(pin) <= 6:
        raise ValueError("PIN must be a 1-6 digit decimal string")
    raw = pin.encode("ascii")
    padded = raw + bytes(30 - len(raw))
    crc = calculate_crc16_a(padded)
    return struct.pack("<H", crc) + padded


# ---------------------------------------------------------------------------
# Cryptography
# ---------------------------------------------------------------------------

def decrypt_challenge(
    message: bytes,
    application_key: bytes = MASTER_APPLICATION_KEY,
) -> bytes:
    """Decrypt the 32-byte challenge read from characteristic cb89eab5 to get the session key."""
    number, fields = parse_message(message)
    if number not in (MSG_CHALLENGE_APTA, MSG_CHALLENGE_ACS) or len(fields) != 1 or len(fields[0]) != 32:
        raise ValueError("Unexpected ACS challenge packet structure")
    if len(application_key) != 32:
        raise ValueError("Expected a 32-byte application key")
    cipher = Cipher(algorithms.AES(application_key), modes.CBC(ZERO_IV)).decryptor()
    return cipher.update(fields[0]) + cipher.finalize()


def build_access_request(
    access_id: str,
    session_key: bytes,
    facility_code: str = "",
) -> bytes:
    """Build Message 6 (Matrix Proprietary Access Request) formatted and encrypted."""
    if len(session_key) != 32:
        raise ValueError("Expected a 32-byte session key")
    cipher = Cipher(algorithms.AES(session_key), modes.CBC(ZERO_IV)).encryptor()
    encrypted_id = cipher.update(access_payload(access_id)) + cipher.finalize()

    fields = [b"\x00", encrypted_id]
    if facility_code:
        cipher_fc = Cipher(algorithms.AES(session_key), modes.CBC(ZERO_IV)).encryptor()
        encrypted_fc = cipher_fc.update(facility_code_payload(facility_code)) + cipher_fc.finalize()
        fields.append(encrypted_fc)

    return frame_message(MSG_ACCESS_REQUEST_MATRIX, fields)


def build_wiegand_request(
    access_id: str,
    session_key: bytes,
    wiegand_format: int = WIEGAND_FORMAT_26BIT,
) -> bytes:
    """Build Message 10 (Wiegand Format Access Request)."""
    if len(session_key) != 32:
        raise ValueError("Expected a 32-byte session key")
    cipher = Cipher(algorithms.AES(session_key), modes.CBC(ZERO_IV)).encryptor()
    encrypted_id = cipher.update(access_payload(access_id)) + cipher.finalize()
    format_byte = bytes([wiegand_format])
    return frame_message(MSG_ACCESS_REQUEST_WIEGAND, [b"\x00", encrypted_id, format_byte])


def build_pin_request(pin: str, session_key: bytes) -> bytes:
    """Build Message 7 (PIN Access Request)."""
    if len(session_key) != 32:
        raise ValueError("Expected a 32-byte session key")
    cipher = Cipher(algorithms.AES(session_key), modes.CBC(ZERO_IV)).encryptor()
    encrypted_pin = cipher.update(pin_payload(pin)) + cipher.finalize()
    return frame_message(MSG_PIN_REQUEST, [b"\x00", bytes([len(pin)]), encrypted_pin])


# ---------------------------------------------------------------------------
# Result Decoding
# ---------------------------------------------------------------------------

def decode_access_result(data: bytes) -> dict[str, Any]:
    """Parse and decode a Message 3 response notification from cb89eab4 into structured human-readable info."""
    number, fields = parse_message(data)
    if number != MSG_ACCESS_RESULT or len(fields) < 1:
        raise ValueError(f"Expected Message 3 result notification, got {number}")

    msg_code = fields[0][0] if len(fields[0]) >= 1 else 0
    reason_code = fields[1][0] if len(fields) > 1 and len(fields[1]) >= 1 else 0

    msg_text = MESSAGE_CODES.get(msg_code, f"Unknown Message ({msg_code})")
    reason_text = ""
    is_allowed = False

    if msg_code == 1:
        is_allowed = True
        reason_text = ALLOWED_REASON_CODES.get(reason_code, f"Reason {reason_code}")
    elif msg_code == 2:
        reason_text = DENIED_REASON_CODES.get(reason_code, f"Reason {reason_code}")
        if reason_code == 6:
            msg_text = "Access Allowed"
            is_allowed = True
    elif msg_code == 3:
        reason_text = WAITING_REASON_CODES.get(reason_code, f"Reason {reason_code}")
    elif msg_code in (8, 19) and reason_code == 0:
        reason_text = "Contact Administrator"

    return {
        "raw_hex": data.hex(),
        "message_number": number,
        "message_code": msg_code,
        "reason_code": reason_code,
        "message_text": msg_text,
        "reason_text": reason_text,
        "is_allowed": is_allowed,
        "fields": [f.hex() for f in fields],
    }


# ---------------------------------------------------------------------------
# Live Execution Client (via Bleak)
# ---------------------------------------------------------------------------

async def execute_ble_access(
    device_mac: str,
    access_id: str,
    facility_code: str = "",
    wiegand_format: int | None = None,
    timeout: float = 12.0,
    verbose: bool = True,
) -> dict[str, Any]:
    """Connect to a Matrix controller over BLE, execute authentication handshake, and return result."""
    from bleak import BleakClient

    if verbose:
        print(f"Connecting to Matrix controller at {device_mac}...")

    result_future: asyncio.Future[bytes] = asyncio.get_running_loop().create_future()

    def notification_handler(sender: Any, data: bytearray) -> None:
        raw = bytes(data)
        if verbose:
            print(f"Received notification from {sender}: {raw.hex()}")
        if not result_future.done():
            result_future.set_result(raw)

    async with BleakClient(device_mac, timeout=timeout) as client:
        if verbose:
            print(f"Connected. Subscribing to notifications on {CB_CHAR_RESPONSE_NOTIFY}...")
        await client.start_notify(CB_CHAR_RESPONSE_NOTIFY, notification_handler)

        if verbose:
            print(f"Reading challenge from {CB_CHAR_RANDOM_KEY_ACS}...")
        raw_challenge = bytes(await client.read_gatt_char(CB_CHAR_RANDOM_KEY_ACS))
        if verbose:
            print(f"Challenge received: {raw_challenge.hex()}")

        session_key = decrypt_challenge(raw_challenge, MASTER_APPLICATION_KEY)
        if verbose:
            print(f"Decrypted ephemeral session key: {session_key.hex()}")

        if wiegand_format is not None:
            request_packet = build_wiegand_request(access_id, session_key, wiegand_format)
            if verbose:
                print(f"Built Wiegand (format {wiegand_format}) request: {request_packet.hex()}")
        else:
            request_packet = build_access_request(access_id, session_key, facility_code)
            if verbose:
                print(f"Built Matrix Proprietary request: {request_packet.hex()}")

        # Write in 20-byte chunks to fit standard ATT MTU
        for offset in range(0, len(request_packet), 20):
            chunk = request_packet[offset : offset + 20]
            if verbose:
                print(f"Writing fragment [{offset}:{offset+len(chunk)}] to {CB_CHAR_REQUEST_WRITE}...")
            await client.write_gatt_char(CB_CHAR_REQUEST_WRITE, chunk, response=True)

        if verbose:
            print("Awaiting result notification from controller...")
        raw_result = await asyncio.wait_for(result_future, timeout=timeout)
        await client.stop_notify(CB_CHAR_RESPONSE_NOTIFY)

        decoded = decode_access_result(raw_result)
        return decoded


# ---------------------------------------------------------------------------
# CLI Entrypoint
# ---------------------------------------------------------------------------

def main() -> None:
    parser = argparse.ArgumentParser(description="Matrix COSEC ACS BLE Protocol Tool")
    parser.add_argument("--mac", default="30:30:D0:2D:B5:58", help="Target controller MAC address")
    parser.add_argument("--id", default="151920472914012647", help="Access ID credential")
    parser.add_argument("--fc", default="", help="Optional facility code")
    parser.add_argument("--wiegand", type=int, default=None, help="Wiegand format: 1 (26-bit), 3 (35-bit), 5 (37-bit)")
    parser.add_argument("--timeout", type=float, default=10.0, help="Connection / response timeout in seconds")

    args = parser.parse_args()

    print("=================================================================")
    print("Matrix COSEC ACS BLE Protocol Runner")
    print(f"Target Controller : {args.mac}")
    print(f"Access ID         : {args.id}")
    print(f"Protocol Mode     : {'Wiegand ' + str(args.wiegand) if args.wiegand else 'Matrix Proprietary'}")
    print("=================================================================")

    try:
        res = asyncio.run(
            execute_ble_access(
                device_mac=args.mac,
                access_id=args.id,
                facility_code=args.fc,
                wiegand_format=args.wiegand,
                timeout=args.timeout,
                verbose=True,
            )
        )
        print("\n=== Result Summary ===")
        print(f"Allowed     : {res['is_allowed']}")
        print(f"Message     : {res['message_text']} (Code {res['message_code']})")
        print(f"Reason      : {res['reason_text']} (Code {res['reason_code']})")
        print(f"Raw Output  : {res['raw_hex']}")
    except Exception as exc:
        print(f"\nExecution error: {exc}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
