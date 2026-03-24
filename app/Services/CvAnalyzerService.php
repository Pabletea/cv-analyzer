<?php

namespace App\Services;


use OpenAI\Laravel\Facades\OpenAI;
use Exception;

class CvAnalyzerService
{
    private string $model = 'llama-3.3-70b-versatile';

    public function analyze(string $cvText): array{

        $prompt = $this->buildPrompt($cvText);

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
    
    private function buildPrompt(string $cvText): string{
        return <<<PROMPT
            Analiza el siguiente CV y devuelve ÚNICAMENTE un JSON con este esquema exacto:

            {
            "score": <número del 0 al 10>,
            "summary": "<resumen profesional en 2-3 frases>",
            "strengths": ["<fortaleza 1>", "<fortaleza 2>", "<fortaleza 3>"],
            "weaknesses": ["<debilidad 1>", "<debilidad 2>"],
            "years_of_experience": <número>,
            "main_skills": ["<skill 1>", "<skill 2>", "<skill 3>"],
            "recommended_role": "<rol recomendado>",
            "fit_for_position": <true o false>,
            "red_flags": ["<red flag si existe>"]
            }

            Criterios de evaluación:
            - fit_for_position es true si el candidato tiene más de 2 años de experiencia en desarrollo backend
            - score refleja la solidez técnica general del perfil
            - red_flags puede ser un array vacío [] si no hay ninguno

            [INICIO_CV]
            {$cvText}
            [FIN_CV]

            Responde SOLO con el JSON. Ningún texto fuera del JSON.
            Responde siempre en inglés.
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
