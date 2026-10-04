$inputFile = ".\crm_software.sql"
$outputFile = ".\crm_software_aiven.sql"

$sql = Get-Content $inputFile -Raw

$primaryKeys = @{
    "clients" = "id"
    "companies" = "id"
    "contacts" = "id"
    "deals" = "id"
    "failed_jobs" = "id"
    "jobs" = "id"
    "job_batches" = "id"
    "leads" = "id"
    "migrations" = "id"
    "model_has_permissions" = "permission_id,model_id,model_type"
    "model_has_roles" = "role_id,model_id,model_type"
    "notifications" = "id"
    "password_reset_tokens" = "email"
    "permissions" = "id"
    "personal_access_tokens" = "id"
    "pipelines" = "id"
    "pipeline_stages" = "id"
    "roles" = "id"
    "role_has_permissions" = "permission_id,role_id"
    "sessions" = "id"
    "users" = "id"
}

# ------------------------------------------------------------
# 1. Add PRIMARY KEY directly inside every CREATE TABLE
# ------------------------------------------------------------

foreach ($table in $primaryKeys.Keys) {

    $key = $primaryKeys[$table]

    $pattern = '(?s)(CREATE TABLE `' + [regex]::Escape($table) + '`\s*\(.*?)(\)\s*ENGINE=InnoDB)'

    $sql = [regex]::Replace(
        $sql,
        $pattern,
        {
            param($match)

            $body = $match.Groups[1].Value
            $end  = $match.Groups[2].Value

            if ($body -notmatch 'PRIMARY KEY') {

                $body = $body.TrimEnd()

                if (-not $body.EndsWith(',')) {
                    $body += ','
                }

                $body += "`r`n  PRIMARY KEY ($key)`r`n"
            }

            return $body + $end
        }
    )
}

# ------------------------------------------------------------
# 2. Remove ONLY the old ADD PRIMARY KEY lines
#    Keep ALTER TABLE and all ADD KEY statements.
# ------------------------------------------------------------

$sql = [regex]::Replace(
    $sql,
    '(?m)^\s*ADD PRIMARY KEY \([^)]+\),?\s*$\r?\n?',
    ''
)

# ------------------------------------------------------------
# 3. Remove only cache index section
#    The cache table itself is not in the dump.
# ------------------------------------------------------------

$sql = [regex]::Replace(
    $sql,
    '(?ms)--\r?\n-- Indexes for table `cache`\r?\n--.*?(?=--\r?\n-- Indexes for table `cache_locks`)',
    ''
)

# ------------------------------------------------------------
# 4. Remove only cache_locks index section
# ------------------------------------------------------------

$sql = [regex]::Replace(
    $sql,
    '(?ms)--\r?\n-- Indexes for table `cache_locks`\r?\n--.*?(?=--\r?\n-- Indexes for table `clients`)',
    ''
)

Set-Content -Path $outputFile -Value $sql -Encoding UTF8

Write-Host ""
Write-Host "Created:"
Write-Host $outputFile
