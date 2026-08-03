<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => ['required', Rule::in([
                'medication', 'exercise', 'nutrition', 'sleep', 
                'hydration', 'weight', 'blood_pressure', 'blood_sugar', 
                'stress', 'other'
            ])],
            'target_value' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
            'target_date' => 'nullable|date|after:today',
            'start_date' => 'nullable|date',
            'is_recurring' => 'boolean',
            'recurrence_pattern' => 'nullable|in:daily,weekly,monthly',
            'icon' => 'nullable|string',
            'color_hex' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'milestones' => 'nullable|array',
            'milestones.*.title' => 'required|string',
            'milestones.*.target_value' => 'required|numeric|min:0.01',
            'reminders' => 'nullable|array',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre de l\'objectif est requis',
            'category.required' => 'La catégorie est requise',
            'target_value.required' => 'La valeur cible est requise',
            'unit.required' => 'L\'unité est requise',
            'target_date.after' => 'La date cible doit être dans le futur',
        ];
    }
}