$inputFile = ".\crm_software.sql"
$outputFile = ".\crm_software_aiven.sql"

$sql = Get-Content $inputFile -Raw

# Primary keys found in the dump
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

foreach ($table in $primaryKeys.Keys) {

    $pattern = "(?s)(CREATE TABLE `$table` \()(.*?)(\)\s*ENGINE=InnoDB)"

    if ($sql -match $pattern) {

        $columns = $Matches[2]

        # Add primary key only if it isn't already inside CREATE TABLE
        if ($columns -notmatch "PRIMARY KEY") {
            $columns = $columns.TrimEnd() + ",`r`n  PRIMARY KEY ($($primaryKeys[$table]))`r`n"
        }

        $replacement = $Matches[1] + $columns + $Matches[3]

        $sql = [regex]::Replace(
            $sql,
            $pattern,
            [System.Text.RegularExpressions.MatchEvaluator]{
                param($match)
                $replacement
            },
            1
        )
    }
}

# Remove the later ALTER TABLE PRIMARY KEY statements
$sql = [regex]::Replace(
    $sql,
    "(?ms)ALTER TABLE `(clients|companies|contacts|deals|failed_jobs|jobs|job_batches|leads|migrations|model_has_permissions|model_has_roles|notifications|password_reset_tokens|permissions|personal_access_tokens|pipelines|pipeline_stages|roles|role_has_permissions|sessions|users)`\s*\r?\n\s*ADD PRIMARY KEY \([^)]+\);\r?\n",
    ""
)

# Remove cache/cache_locks index sections because those CREATE TABLE
# definitions are not present in this dump
$sql = [regex]::Replace(
    $sql,
    "(?ms)--\r?\n-- Indexes for table `cache`\r?\n--\r?\nALTER TABLE `cache`.*?;\r?\n\r?\n",
    ""
)

$sql = [regex]::Replace(
    $sql,
    "(?ms)--\r?\n-- Indexes for table `cache_locks`\r?\n--\r?\nALTER TABLE `cache_locks`.*?;\r?\n\r?\n",
    ""
)

Set-Content -Path $outputFile -Value $sql -Encoding UTF8

Write-Host "Created: $outputFile"
