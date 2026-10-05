# Local workflow guide

All sample people and AI scores in the default sandbox are synthetic. No live service is required for the text-based workflow. Start the web server, notification worker, AI generation/grading workers and scheduler from README.

## Coordinator

1. Sign in as Casey. Open Sam's enrolment and **Onboarding and plan setup**. Complete the six input steps, confirm the assessment and shared calendar, then create a curriculum draft.
2. Write every lesson or open **Generate or revise with AI** on an editable draft. Run the generation worker and refresh the job page. Review all blocks, request review and approve the exact version.
3. Open **Today and learning plan** and activate the approved programme. Future lessons are preview-only. For an accelerated synthetic demonstration, explicitly authorise early start on a block. Starting or saving work locks that block against adaptation.
4. Before a block starts or freezes, its lesson page optionally offers **Publish an objective quiz**. Enter one objectively verifiable question for each criterion, 2–4 options and a private correct option. Published questions and keys cannot be overwritten. Objective answers are marked in application code, without an AI call; human approval remains required.
5. Open a submission receipt. **Request provisional grading** queues AI analysis for practical work, or computes deterministic quiz results. AI does not inspect attached binary files; unread files force human evidence review rather than an automatic passing score. The deterministic mock returns demonstration scores only.
6. Review criterion feedback and supplied evidence. Approve, request changes, or use **Human assessment or override**. Record evidence and rationale. Regrading preserves the old approved decision until a new one is approved. Requested changes are visible to the member; resubmission creates a new immutable attempt.
7. Open **Progress, KPIs and future support**. Approved grade observations retain decision/submission/rubric lineage. Below-minimum samples display insufficient evidence, not zero. Effective-dated KPI revisions do not rewrite historical snapshots. Workplace measurements use separately identified manual evidence.
8. Request support suggestions or a future adaptation from an approved evidence block N. The first eligible target is N+2, then later blocks. Review the proposed teaching text before approval. Dates, capacity, prerequisite order, competencies and criteria are protected; eligibility and source versions are checked again at approval. If no target remains, discuss final support or a follow-on programme.
9. Pause for leave, preview an explicit future schedule and approve the preview to resume. Started work remains unchanged. Capacity is checked across concurrent programmes. Completion requires every required lesson's approved evidence, sufficient current KPI results and a coordinator capability confirmation.

## Member

Sign in as Sam. Open the enrolment, then **Today and learning plan**. Choose an available lesson, complete the activity and type **Your response** or select objective answers. Wait for “Draft saved”, then **Submit saved work for review**. Keep the receipt. Reloading restores the saved draft; network/conflict errors retain unsaved text in the form. No assessment text is stored in localStorage.

Pending/provisional numeric grades are hidden. Approved feedback and requested changes are visible. Withdraw an unapproved attempt to correct it, or revise after requested changes. An approved decision can be appealed. An administrator assigns an alternate coordinator; the original decision remains official while review is open.

Use **Updates** for in-app notices and the email preference. Synthetic notices use a non-delivering sink. Email failure cannot remove the review queue. Relay acceptance is not proof of delivery; ambiguous SMTP outcomes are held without automatic resend.

## Administrator

**Administration** creates local people/cohorts, updates group membership/coordinator assignments and creates enrolments. Assign the member and accountable coordinator to the cohort first. Directory-owned memberships cannot be overwritten locally. Changes increment permission versions and revoke existing affected sessions.

Synthetic people require example.invalid email addresses and remain in test scope. Real local-only people need verified identity mapping before sign-in or promotion; live identity is unavailable in this sandbox. Promotion records retain the original idempotency key. Ambiguous outcomes are reconciled by that key. An acknowledgement must confirm both local person and immutable identity mapping. History export is a separate explicit decision. The default primary adapter makes no network requests and leaves requests prepared.

**AI settings** versions per-task provider routing. Live provider profiles remain disabled until task-specific model/data/pricing qualification and credentials are configured. Mock scores are never calibration evidence.

## Files and limits

Uploads are private and quarantined. Supported extensions are PDF/PNG/JPEG/TXT/DOCX; DOCX requires PHP ZipArchive and inspection that rejects embedded content, macros, traversal paths and excessive expanded size. A configured ClamAV CLI runs in the extraction queue with a 90-second bound. Scanner failure or absence never marks a file clean. The workstation has no qualified scanner or PHP zip extension, so text submissions are the fully usable local path; DOCX and live malware-scan qualification remain launch gates.

Sensitive assessment access remains a separate capability. A member can attach/download their own clean **supporting evidence** uploads. Labelling a file as supporting evidence does not grant access to someone else's file. Assessment files now offer **Extract and review** to the accountable coordinator. TXT/DOCX/PDF extraction and image/scanned-PDF OCR run only after a clean scan, with configured host tools. Correct the extracted findings and explicitly confirm them into a new onboarding version. Manual entry remains available. See [document processing](DOCUMENT_PROCESSING.md) for setup, limits and verification boundaries.
