# Document processing and assessment extraction

This milestone adds local document parsing and a coordinator review workflow. Extraction never approves an assessment, grades work or sends raw documents to an AI provider. Original files remain private; extracted text and review drafts are encrypted at rest using the application key.

## Using the workflow

1. Open an enrolment that is still in onboarding or ready. Upload a file with purpose **Sensitive assessment for coordinator review** under **Private evidence**. PNG/JPEG images are also accepted for OCR; use only authorised assessment material.
2. Sign in as its accountable coordinator with sensitive-assessment access. Select **Extract and review**, then **Request extraction**. You can also reach the file list from the Assessment findings step.
3. Quarantined/scanning files show **waiting scan**. Only the configured scanner may mark them clean. Rejected/deleted files cannot be processed. No UI switch bypasses scanning.
4. The `extraction` queue processes clean files. Refresh the status page. Read the warnings and compare the read-only extraction with the original private download.
5. Enter the provider/observer, assessment date, scoring scale, evidence type and a corrected summary of the relevant findings (up to 4,000 characters). **Save review draft** preserves edits without changing onboarding. The source text stays unchanged.
6. **Confirm reviewed findings** creates a new confirmed onboarding version with file hash, extraction ID/revision and coordinator attribution. A stale extraction or onboarding version conflicts rather than overwriting newer work. Review/refresh the curriculum against the changed inputs before approval.
7. If parsing fails, use **Retry extraction** after fixing the cause or **Use manual assessment entry**. A new attempt preserves failure metadata. Do not open a rejected file to work around quarantine.

Members, unrelated coordinators and administrators without accountable coordinator authority cannot read raw extraction/review pages. Confirmed assessment findings follow the existing onboarding permissions. Active programme onboarding cannot be replaced through this workflow.

## Formats and limits

| Format | Behaviour |
| --- | --- |
| TXT | UTF-8 text; invalid encoding and empty content fail visibly. |
| DOCX | Main document body paragraphs/table cells. Rejects macros, embedded/active objects, external relationships, DTDs/entities, encrypted entries and suspicious archive paths. Headers, footnotes, drawings and image-only material require comparison/manual findings; DOCX pagination is not inferred. |
| PDF | Poppler reads each page. Pages with fewer than 20 text characters use rasterisation and Tesseract OCR; mixed text/scanned PDFs retain page markers. A page yielding no text fails the run rather than silently dropping it. Embedded diagrams on otherwise text-rich pages still need manual review. |
| PNG/JPEG | Tesseract OCR with confidence warnings. Numbers, dates and scores always require human checking. |

Limits: 20 MiB upload, 20 PDF pages, 120,000 UTF-8 extracted bytes, 25 megapixels per image, 2,200-pixel maximum raster edge, 500 DOCX entries, 100 MiB total expanded ZIP size and 8 MiB per entry. The subprocess has a 128 MiB PHP memory limit and 90-second overall timeout; each external command has at most 25 seconds. Native tools require host-level memory/CPU limits too. Results are never silently truncated into an approved finding.

DOCX is read without extracting archive paths. XML external entity substitution is disabled and external relationships are rejected; see the [PHP DOM documentation](https://www.php.net/domdocument). OCR uses Tesseract's TSV output to retain word confidence; see [Tesseract command-line documentation](https://tesseract-ocr.github.io/tessdoc/Command-Line-Usage.html). Text remains untrusted and is HTML-escaped on display.

## Host setup

Install and maintain ClamAV with current production signatures, Poppler utilities, Tesseract with the required language data, and PHP ZipArchive in both CLI and web runtimes. On a qualified Debian/Ubuntu host, package names are `clamav`, `clamav-freshclam`, `poppler-utils`, `tesseract-ocr`, `tesseract-ocr-eng`, `php-zip` and `bubblewrap`; validate availability against the actual host/runtime.

Example `.env` paths for that host:

```dotenv
TRAINING_CLAMSCAN_BINARY=/usr/bin/clamscan
DOCUMENT_PDFINFO=/usr/bin/pdfinfo
DOCUMENT_PDFTOTEXT=/usr/bin/pdftotext
DOCUMENT_PDFTOPPM=/usr/bin/pdftoppm
DOCUMENT_TESSERACT=/usr/bin/tesseract
DOCUMENT_OCR_LANGUAGE=eng
DOCUMENT_SANDBOX_WRAPPER=/srv/training/platform/deploy/document-sandbox.sh
```

Use absolute executable paths and language data installed by the operator. Never populate tool paths from uploaded content. `TRAINING_CLAMAV_DATABASE` is an optional operator-controlled override; normally omit it so ClamAV uses its maintained system signature database. A custom test-signature database is not production protection.

`deploy/document-sandbox.sh` is a Linux/Bubblewrap template that denies network access and exposes only the parser/runtime, selected system paths and one private working directory. Install it executable and qualify the mount paths, user namespace policy and tool locations. It assumes `/usr/bin` utilities and `/etc/php`; adapt and validate for the chosen host. Application `.env`, the database and other private files are not mounted. Apply worker-level memory/process/CPU controls in addition to command timeouts. The template is not proof of production sandbox qualification.

The application refuses production extraction without a configured wrapper. Local/testing runs may use the bounded subprocess without OS isolation for original synthetic fixtures only. The subprocess does not bootstrap Laravel, read `.env` or inherit application credentials. Native tool stdout/stderr and raw assessment text are not written to application logs.

At initial implementation, the Windows workstation's default PHP lacked the zip extension and had no ClamAV/Poppler/Tesseract on PATH. DOCX parser tests can explicitly enable the installed `C:/xampp/php/ext/php_zip.dll` for that test process. This does not enable it in the web server or worker, and does not install the other tools. Keep uploads quarantined until real local tools/signatures are configured. A clean seeded text fixture may demonstrate extraction, but it is not evidence of malware scanning.

## Jobs, recovery and privacy

Run a worker consuming `extraction` with timeout 120 and queue retry-after 180. The scheduler runs `training:extract-recover` every minute and existing scan recovery every five minutes. Requests and audits commit before dispatch. Duplicate dispatches claim one lease; interrupted parsing becomes an explicit retry after 180 seconds. Authorization, current source hash and scan status are checked before parsing, before result publication and at confirmation. Private working copies are deleted when processing finishes or stale runs are recovered.

Source retention/deletion replay also clears derived extraction text and review drafts. Legal holds apply through the source enrolment's existing evidence policy. Confirmed onboarding snapshots remain training history under the existing retention rules; this milestone does not implement complete subject erasure. Protect the application key in backup and recovery, since it decrypts extraction data.

`training:health` includes counts of failed extractions and requests waiting for scans, without document contents. Failed runs have stable safe error codes and manual-entry instructions.

## Verification

The feature suite covers actual TXT subprocess execution, encrypted storage, escaped rendering, review/save/confirm, immutable provenance, stale versions, role boundaries, revocation during processing, source hash changes, interrupted jobs, manual fallback and deletion replay. Parser tests cover UTF-8/limits, DOCX body/table text and malicious archive/XML references. The browser test exercises review editing, reload persistence, confirmation, mobile accessibility and member denial.

The dedicated CI utility job installs real Poppler/Tesseract/ClamAV and requires them rather than skipping their checks. It tests text PDF extraction, image OCR, mixed text/scanned PDF fallback, and a harmless custom-signature clean/reject scanner flow. That scanner test verifies engine integration, not current production signature coverage or protection against all malicious documents.

Local commands from `platform/`:

```powershell
php artisan test --filter=DocumentExtractionTest
php -d extension=C:/xampp/php/ext/php_zip.dll vendor/bin/phpunit --filter=DocumentParserTest --testdox
npm.cmd run test:browser
```

Real-utility tests skip when tools are absent locally. Set `DOCUMENT_REQUIRE_TOOLS=1` only in a runtime that should have all tools; missing dependencies then fail the checks. Production host isolation, multilingual/handwritten OCR quality and representative assessment interpretation still need qualification.
