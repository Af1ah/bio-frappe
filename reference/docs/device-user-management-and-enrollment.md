# Device User Management & Biometric Enrollment Reference

Status: Completed Checkpoints + Next Step Implementation Guide  
Target: `/home/aflah/projects/bio-notifier`

---

## 1. Completed Checkpoints Summary

### A. Device Enrollment Configuration Checkboxes
- **Methods supported**:
  - `fingerprint`: Fingerprint template biometric enrollment
  - `rfid`: RFID Card verification & card number mapping
  - `face`: Standard/legacy face biometric template (v1)
  - `face_v2`: Visible light / AI device face recognition (e.g., SpeedFace, FaceDepot, Horus)
- **UI & Persistence**:
  - Available as checkboxes in both **Add Device** modal and **Edit Device** form.
  - Stored in `device.options.enrollment_methods` and synced to `capabilities` in central `device_bindings`.
  - Displayed with visual badges on the Devices table.
  - Helper methods on `Device`:
    - `supportedEnrollmentMethods(): array`
    - `supportsEnrollment(string $method): bool`

### B. Encrypted Credential & Template Storage
- Sensitive biometric data and credentials in the `users` table are encrypted in the database:
  - `device_password`: Encrypted string
  - `fingerprints`: Encrypted JSON array of templates
  - `face_templates`: Encrypted JSON array of standard face templates
  - `face_v2_templates`: Encrypted JSON array of AI face biodata / biophoto
- **Implementation**:
  - `App\Casts\EncryptedArrayCast`
  - `App\Casts\EncryptedStringCast`
- **Security & Backwards Compatibility**:
  - Transparently encrypts on save using Laravel AES-256 (`Crypt`).
  - Stores as valid JSON envelopes `{"encrypted": true, "payload": "..."}` fully compatible with PostgreSQL JSON columns and SQLite.
  - Fallback logic safely reads legacy unencrypted JSON arrays and plaintext strings without throwing `DecryptException`.

### C. Checkpoint 1: Delete User from Device
- **Wire Command**: `DATA DELETE USERINFO PIN={PIN}`
- **Capabilities & Dispatch**:
  - Integrated into `AdmsCommandPayloadBuilder`, `DeviceCommandCapabilities`, and `DeviceCommandDispatcher`.
  - Available as:
    - User single action: **Delete from Device** (prompts device selection).
    - Device single action: **Delete User from Device** (selects enrolled user or enters PIN).
    - User bulk action: **Delete from Devices** (batches across multiple devices).
  - Automatically updates local `device_users` tracking table.

### D. Checkpoint 2: Upload User with Credentials Separately
- **Separate Credential Selection**:
  - `pin`: PIN, Name, Privilege, and Device Password
  - `card`: RFID Card Number
  - `fingerprint`: Fingerprint Templates
  - `face`: Face Templates (automatically routes v1 or v2 according to device capability)
- **Wire Commands Generated**:
  - Base User Info: `DATA UPDATE USERINFO PIN={pin}\tName={name}\tPrivilege={priv}\tCard={card}\tPassword={password}`
  - Fingerprint: `DATA UPDATE FINGERTMP PIN={pin}\tFID={fid}\tSize={size}\tValid=1\tTMP={template}`
  - Face v1: `DATA UPDATE USERFACE PIN={pin}\tFID=0\tSize={size}\tValid=1\tTMP={template}`
  - Face v2 (AI): `DATA UPDATE BIODATA Pin={pin}\tNo=0\tIndex=0\tValid=1\tDuress=0\tType=9\tMajorVer=12\tMinorVer=0\tFormat=0\tTmp={template}` (or `BIOPHOTO`)
- **Copying/Transferring Between Devices**:
  - **Copy Users from Device** action allows selecting a source device, choosing credentials, and queuing the users for upload to the destination terminal.

---

## 2. Next Step Checkpoints & Implementation Specification

> [!IMPORTANT]
> Keep this section as the direct blueprint for the next task. No additional reverse-engineering is required.

### Next Step Objectives:
1. **Show Device Available Users**:
   - In Device details or relation manager, list users that currently exist on the device.
2. **Select & Edit Device Users**:
   - Edit an enrolled user's attributes (Name, Card, Privilege, Password) directly on the terminal.
3. **Enroll Existing Software Users to Device**:
   - Pick users already registered in software and upload them to the device with selected credentials or trigger live biometric capture.

### Wire Protocols & Data Flow:

#### 1. Fetching / Refreshing Users on Device
- **Command to Device**: `DATA QUERY USERINFO`
- **Device Response**:
  - Device responds to `GET /iclock/getrequest` by executing the query.
  - Device then POSTs user lines to `/iclock/cdata?table=USERINFO`:
    ```
    PIN=1001	Name=John Doe	Privilege=0	Card=99887766	Password=1234
    PIN=1002	Name=Jane Smith	Privilege=14	Card=11223344
    ```
- **Local Persistence**:
  - Ingested by `GatewayEventIngestor`.
  - Upserted into tenant table `device_users`:
    - `device_id` (foreign key to `devices.id`)
    - `pin` (string)
    - `name` (string)
    - `card_number` (string, nullable)
    - `privilege` (integer: 0=User, 14=Admin)
    - `fingerprint_count`, `face_count`
    - `last_seen_at` (timestamp)

#### 2. Showing Device Available Users (UI Component)
- **Table / Relation Manager**:
  - Add `DeviceUsersRelationManager` to `DeviceResource`.
  - Columns:
    - PIN
    - Name (links to Software User if matched)
    - Card Number
    - Privilege (Badge: Normal / Admin)
    - Fingerprints & Face indicators
    - Last Seen on Device
  - Header Action: **Refresh Users from Terminal** (`DATA QUERY USERINFO`).

#### 3. Editing a Device User
- **Action**: Row action on `DeviceUsersRelationManager` or modal on Device: **Edit on Device**.
- **Form Fields**:
  - Name
  - Privilege (User / Admin)
  - Card Number
  - Device Password
- **Execution**:
  - Dispatches `DeviceCommand` with `command_type => 'upload_user'`.
  - Wire command sent on next poll:
    `DATA UPDATE USERINFO PIN={pin}\tName={name}\tPrivilege={priv}\tCard={card}\tPassword={password}`
  - Updates `device_users` row and optionally syncs back to software `User`.

#### 4. Enrolling Software Users into Device
- **Action**: **Enroll Users into Device** on Device resource.
- **Form**:
  - Multi-select software `User` records.
  - Credential checklist (`pin`, `card`, `fingerprint`, `face`).
- **Execution**:
  - Calls `UserDeviceSyncService::uploadUserToDevice($device, $user, $credentials)`.
- **Live Terminal Capture (Optional prompt)**:
  - If triggering biometric capture at the physical terminal:
    - Fingerprint: `ENROLL_FP PIN={pin}\tFID={fid}\tRETRY=3\tOVERWRITE=1`
    - Face: `ENROLL_FACE PIN={pin}`
  - Terminal beeps and prompts user on LCD to scan finger or face.
