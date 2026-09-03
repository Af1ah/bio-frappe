# Biomatrix Development Reference

## Project

Biomatrix is a multi-tenant Laravel 12 and Filament application that manages biometric attendance devices directly. It is the companion tool for Matrix and supports ZKTeco ADMS, Matrix COSEC, and Hikvision devices.

## Direct-device architecture

- **ZKTeco ADMS:** Devices poll `/iclock/cdata`, `/iclock/getrequest`, and `/iclock/devicecmd`. Tenant resolution is performed by serial number in `IdentifyTenantByDeviceSN`.
- **Matrix COSEC:** HTTP actions are handled by `ProcessMatrixCommand`; the device push endpoints are `/login` and `/matrix/*`.
- **Hikvision:** ISAPI actions are handled by `ProcessHikvisionCommand`.
- **Commands:** Create device commands with `app/Services/Attendance/DeviceCommandBuilder.php`. Its methods represent the shared user, fingerprint, sync, cleanup, and device-control commands.
- **Biometric templates:** The `fingerprints` JSON field stores templates. Pull users from a device to capture templates, then select **Push to Device** in the Users table to distribute profiles and fingerprints.

## Multi-tenancy

Initialize tenancy in every queued job before accessing tenant models. Device polling middleware selects the tenant before the controller executes. Ensure new device operations work from an initialized tenant database.

## Key paths

- Device UI: `app/Filament/Tenant/Resources/DeviceResource.php`
- User creation, import and profile/biometric push: `app/Filament/Tenant/Resources/UserResource.php`
- Direct device service: `app/Services/Attendance/DirectDeviceService.php`
- Command builder: `app/Services/Attendance/DeviceCommandBuilder.php`
- ADMS controllers: `app/Http/Controllers/Api/Attendance/`
