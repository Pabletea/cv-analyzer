# CV Analyzer API

REST API developed in Laravel that automatically analyzes CVs using AI (LLMs).
It receives a CV in PDF or TXT, extracts the text, processes it with a language model,
and returns a structured report with a score, strengths, weaknesses, and recommendations.

## Technical Stack

- **PHP 8.x + Laravel 13**
- **SQLite** as the database
- **Laravel Queues** for asynchronous processing
- **Groq API (LLaMA 3.3)** as the language model
- **smalot/pdfparser** for PDF text extraction
- **Batch processing** for bulk CV uploads with real-time progress tracking
- **Candidate ranking** system based on AI-generated scores

## Arquitecture

The analysis flow is fully asynchronous to avoid timeouts on long requests.
The client receives an immediate response with an ID and can check the status until the analysis is completed.

```
POST /api/cv/analyze
       │
       ▼
CvAnalysisController → saves to DB (status: pending)
       │
       ▼
AnalyzeCvJob (queue) → PdfExtractorService → CvAnalyzerService → OpenAI API
       │
       ▼
BD actualizada (status: completed + result)
       │
       ▼
GET /api/cv/{id}/report → returns the report
```

### Batch analysis flow

When multiple CVs are submitted in a single request, each one is dispatched
as an independent job. Progress is tracked in real time via the batch status
endpoint, and once all jobs are completed, the ranking endpoint returns
candidates sorted by their AI-generated score.

```
POST /api/batch/analyze
       │
       ▼
CvBatch created (status: processing, total: N)
       │
       ├── AnalyzeCvJob (cv_1) ──┐
       ├── AnalyzeCvJob (cv_2) ──┤── each job increments batch.processed
       └── AnalyzeCvJob (cv_N) ──┘
                                 │
                                 ▼
                    batch.processed == batch.total
                    batch.status = completed
                                 │
                                 ▼
             GET /api/batch/{id}/ranking → sorted by score DESC
```

## Technical Decisions

**Why asynchronous processing?**\
LLM calls can take between 5 and 30 seconds. Making the call synchronously would block the server and result in a poor user experience. With queues, the endpoint responds in <100ms and the analysis is processed in the background.

**Why separate PdfExtractorService and CvAnalyzerService?**\
Single Responsibility Principle. Each service has a clear responsibility: one extracts text, the other analyzes it. If we change the LLM or the PDF parser in the future, we only need to modify one file.

**Why validate the JSON structure returned by the AI?**\
LLMs do not always respond in the expected format. Validation in
CvAnalyzerService::validateStructure() ensures that if the AI returns something unexpected, the error is cleanly handled instead of propagating corrupted data.

**Why is each CV in a batch processed as an independent job?**\
Isolating each CV into its own job means a single failure does not block
the rest of the batch. If one CV is corrupted or the LLM returns an
unexpected response, that job fails gracefully while all others continue
processing normally.

**Why sort the ranking in PHP instead of SQL?**\
The score lives inside a JSON column, which makes SQL sorting either
unavailable or unreliable depending on the database engine. Sorting in
PHP with `sortByDesc` on the collection is explicit, portable, and easy
to extend — for example, adding secondary sorting criteria like
`years_of_experience` requires a single line change.


## Installation

### 1. Clone the repository

```bash
git clone https://github.com/tu-usuario/cv-analyzer.git
cd cv-analyzer
```

### 2. Install the dependencies
```bash
composer install
```

### 4. Configure the environment
```bash
cp .env.example .env
php artisan key:generate
```
Edit `.env` and add your API key:
```env
OPENAI_API_KEY=your-api-key
OPENAI_BASE_URI=api.groq.com/openai/v1
QUEUE_CONNECTION=database
DB_CONNECTION=sqlite
```

### 4.Prepare the database

```bash
touch database/database.sqlite
php artisan migrate
```
### 5. Start the server and the worker
```bash
php artisan serve
php artisan queue:work
```

## Endpoints

### POST /api/cv/analyze
Upload yout CV for analyzing

**Request**
```
Content-Type: multipart/form-data
cv: [file .pdf o .txt, max 5MB]
```

**Response 202:**
```json
{
  "id": 1,
  "status": "pending",
  "message": "CV received successfully. Processing analysis."
}
```

### GET /api/cv/{id}/status
Check the analysis status.

**Response:**

```json
{
  "id": 1,
  "status": "completed"
}
```

### GET /api/cv/id/report
Gets the full report

**Response:**
```json
  "id": 1,
  "filename": "cv_ana_garcia.pdf",
  "result": {
    "score": 7.5,
    "summary": "Solid technical profile with 3 years of experience in backend PHP...",
    "strengths": ["MySQL", "REST APIs", "Laravel"],
    "weaknesses": ["Testing", "Cloud experience"],
    "years_of_experience": 3,
    "main_skills": ["PHP", "MySQL", "Laravel", "Docker"],
    "recommended_role": "Mid Backend Developer",
    "fit_for_position": true,
    "red_flags": []
  }
```

### POST /api/batch/analyze
Submit multiple CVs for bulk analysis under a single configuration.

**Request:**
```
Content-Type: multipart/form-data
cvs[]:      [file 1 — .pdf or .txt, max 5MB]
cvs[]:      [file 2 — .pdf or .txt, max 5MB]
config_id:  1 (optional)
```

**Response 202:**
```json
{
  "batch_id": 1,
  "total": 3,
  "status": "processing",
  "message": "3 CVs received. Processing in background."
}
```

---

### GET /api/batch/{id}/status
Check the processing progress of a batch.

**Response:**
```json
{
  "batch_id": 1,
  "status": "processing",
  "total": 3,
  "processed": 2,
  "progress": "66%"
}
```

---

### GET /api/batch/{id}/ranking
Returns all candidates in the batch sorted by score from highest to lowest.
Only available when `status` is `completed`.

**Response:**
```json
{
  "batch_id": 1,
  "status": "completed",
  "config": {
    "name": "Backend PHP Senior",
    "position": "Backend Developer PHP",
    "min_years_experience": 3,
    "required_skills": ["PHP", "MySQL", "Laravel"]
  },
  "total": 3,
  "processed": 3,
  "ranking": [
    {
      "rank": 1,
      "filename": "cv_ana_garcia.pdf",
      "score": 8.5,
      "summary": "Solid backend profile with 4 years of PHP experience...",
      "fit_for_position": true,
      "recommended_role": "Backend Developer Mid-Senior",
      "main_skills": ["PHP", "Laravel", "MySQL"]
    },
    {
      "rank": 2,
      "filename": "cv_pedro_lopez.pdf",
      "score": 7.0,
      "summary": "Junior-mid developer with strong MySQL skills...",
      "fit_for_position": true,
      "recommended_role": "Backend Developer Junior-Mid",
      "main_skills": ["PHP", "MySQL", "REST APIs"]
    },
    {
      "rank": 3,
      "filename": "cv_luis_martin.pdf",
      "score": 4.5,
      "summary": "Frontend-oriented profile with limited backend experience...",
      "fit_for_position": false,
      "recommended_role": "Junior Frontend Developer",
      "main_skills": ["JavaScript", "React", "CSS"]
    }
  ]
}
```
