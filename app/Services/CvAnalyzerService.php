<?php

namespace App\Services;

use App\Models\AnalysisConfig;
use OpenAI\Laravel\Facades\OpenAI;
use Exception;

class CvAnalyzerService
{
    private string $model = 'llama-3.3-70b-versatile';

    public function analyze(string $cvText, ?AnalysisConfig $config = null): array{

        $prompt = $this->buildPrompt($cvText, $config);

        $response = OpenAI::chat()->create([
            'model'       => $this->model,
            'temperature' => 0.3,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'Eres un experto en selección de personal técnico. 
                                  Responde ÚNICAMENTE con JSON válido, sin texto adicional, 
                                  sin bloques de código, sin explicaciones.'
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt
                ]
            ]
        ]);

        $content = $response->choices[0]->message->content;

        return $this->parseResponse($content);
    }
    
    private function buildPrompt(string $cvText, ?AnalysisConfig $config = null): string{

        // Criterios dinámicos según la config
        $position    = $config?->position ?? 'backend developer';
        $minYears    = $config?->min_years_experience ?? 2;
        $skills      = $config?->required_skills
            ? implode(', ', $config->required_skills)
            : 'PHP, MySQL, APIs REST';
        $extraPrompt = $config?->prompt_extra
            ? "\nInstrucciones adicionales: {$config->prompt_extra}"
            : '';


        return <<<PROMPT
            Analyze the following CV for the position of {$position} and return ONLY a JSON with this exact schema:

            {
            "score": <number from 0 to 10>,
            "summary": "<professional summary in 2-3 sentences>",
            "strengths": ["<strength 1>", "<strength 2>", "<strength 3>"],
            "weaknesses": ["<weakness 1>", "<weakness 2>"],
            "years_of_experience": <number>,
            "main_skills": ["<skill 1>", "<skill 2>", "<skill 3>"],
            "recommended_role": "<recommended role>",
            "fit_for_position": <true or false>,
            "red_flags": ["<red flag if any>"]
            }

            Evaluation criteria:
            - fit_for_position is true if the candidate has at least {$minYears} years of experience
            - The following skills will be especially valued: {$skills}
            - score reflects how well the profile fits the position of {$position}{$extraPrompt}
            - Always respond in English, regardless of the CV language

            [CV_START]
            {$cvText}
            [CV_END]

            Respond ONLY with the JSON. No text outside the JSON.
            PROMPT;
    }

    private function parseResponse(string $content):array{

        $content = preg_replace('/```json|```/', '', $content);
        $content = trim($content);

        $data = json_decode($content, true);

        if(json_last_error() !== JSON_ERROR_NONE){
            throw new Exception("La respuesta del modelo devolvió un formato no válido: " . json_last_error());
        }

        return $this->validateStructure($data);
    }

    private function validateStructure(array $data):array{

        $required = [
            'score', 'summary', 'strengths', 'weaknesses',
            'years_of_experience', 'main_skills',
            'recommended_role', 'fit_for_position', 'red_flags'   
        ];

        foreach($required as $field){
            if(!array_key_exists($field,$data)){
                throw new Exception("Falta el siguiente campo obligatorio en la respuesta: {$field}");
            }
        }

        return $data;
    }
}
