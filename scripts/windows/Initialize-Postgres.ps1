[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [SecureString]$PostgresAdminPassword,
    [string]$PsqlPath = 'C:\Program Files\PostgreSQL\18\bin\psql.exe'
)

$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $PsqlPath)) {
    throw "psql was not found at $PsqlPath"
}

$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($PostgresAdminPassword)
try {
    $env:PGPASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    $roleExists = & $PsqlPath -X -w -h 127.0.0.1 -p 5432 -U postgres -d postgres -tAc "SELECT 1 FROM pg_roles WHERE rolname = 'essl'"
    if ($LASTEXITCODE -ne 0) { throw 'Could not authenticate to PostgreSQL as postgres.' }

    if ($roleExists.Trim() -ne '1') {
        & $PsqlPath -X -w -h 127.0.0.1 -p 5432 -U postgres -d postgres -c "CREATE ROLE essl LOGIN PASSWORD 'essl';"
        if ($LASTEXITCODE -ne 0) { throw 'Could not create PostgreSQL role essl.' }
    }

    $databaseExists = & $PsqlPath -X -w -h 127.0.0.1 -p 5432 -U postgres -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = 'bio-frappe'"
    if ($LASTEXITCODE -ne 0) { throw 'Could not check for the PostgreSQL database.' }

    if ($databaseExists.Trim() -ne '1') {
        & $PsqlPath -X -w -h 127.0.0.1 -p 5432 -U postgres -d postgres -c 'CREATE DATABASE "bio-frappe" OWNER essl;'
        if ($LASTEXITCODE -ne 0) { throw 'Could not create database bio-frappe.' }
    }
}
finally {
    Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }
}

Write-Host 'PostgreSQL role essl and database bio-frappe are ready.'
