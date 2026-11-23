#!/bin/sh
# Instructor/after-lab helper for Lab 5: copies the reference solution from ./solutions/lab5,
# restarts the app (new migration, disk config, PHP limits) and runs the security tests.
# Run from the project root on the HOST. Requires a rebuilt image (docker compose up -d --build).
set -e

S=solutions/lab5
mkdir -p app/Services app/Console/Commands tests/Feature/Security

cp $S/StoreDocumentRequest.php      app/Http/Requests/StoreDocumentRequest.php
cp $S/FileSignatureValidator.php    app/Services/FileSignatureValidator.php
cp $S/SimulatedMalwareScanner.php   app/Services/SimulatedMalwareScanner.php
cp $S/Document.php                  app/Models/Document.php
cp $S/DocumentController.php        app/Http/Controllers/DocumentController.php
cp $S/DocumentPolicy.php            app/Policies/DocumentPolicy.php
cp $S/ScanQuarantinedDocuments.php  app/Console/Commands/ScanQuarantinedDocuments.php
cp $S/SecurityHeaders.php           app/Http/Middleware/SecurityHeaders.php
cp $S/2026_01_01_000008_create_documents_table.php database/migrations/2026_01_01_000008_create_documents_table.php
cp $S/security-demo.blade.php       resources/views/security-demo.blade.php
cp $S/filesystems.php               config/filesystems.php
cp $S/lab5.ini.hardened             docker/php/lab5.ini
cp $S/web.php                       routes/web.php
cp $S/bootstrap-app.php             bootstrap/app.php
cp $S/tests/*.php                   tests/Feature/Security/

docker compose restart app
sleep 8
docker compose exec app php artisan test tests/Feature/Security/FileUploadSecurityTest.php tests/Feature/Security/CspSecurityTest.php
