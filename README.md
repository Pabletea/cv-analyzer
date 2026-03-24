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

## Technical Decisions

**Why asynchronous processing?**\
LLM calls can take between 5 and 30 seconds. Making the call synchronously would block the server and result in a poor user experience. With queues, the endpoint responds in <100ms and the analysis is processed in the background.

**Why separate PdfExtractorService and CvAnalyzerService?**\
Single Responsibility Principle. Each service has a clear responsibility: one extracts text, the other analyzes it. If we change the LLM or the PDF parser in the future, we only need to modify one file.

**Why validate the JSON structure returned by the AI?**\
LLMs do not always respond in the expected format. Validation in
CvAnalyzerService::validateStructure() ensures that if the AI returns something unexpected, the error is cleanly handled instead of propagating corrupted data.

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
