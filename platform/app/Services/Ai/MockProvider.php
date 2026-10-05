<?php

namespace App\Services\Ai;

class MockProvider implements Provider
{
    public function generate(array $configuration, array $input): ProviderResult
    {
        if (($input['task'] ?? '') === 'grade_submission') {
            $hasText = strlen(trim($input['evidence'][0]['text'])) >= 30;
            $result = ['rubric_id' => $input['rubric']['id'], 'rubric_version' => $input['rubric']['version'],
                'criteria' => array_map(fn ($criterion) => ['id' => $criterion['id'], 'score' => $hasText ? 70 : 0,
                    'evidence_ids' => [$input['evidence'][0]['id']], 'feedback' => 'Synthetic demonstration score only. A human must independently review the supplied work against this criterion.',
                    'insufficient_evidence' => ! $hasText || count($input['unread_files']) > 0], $input['rubric']['criteria'])];

            return new ProviderResult(json_encode($result, JSON_THROW_ON_ERROR), 100, 100, 'mock-grade');
        }
        if (($input['task'] ?? '') === 'propose_adaptation') {
            $block = $input['replacement'];
            foreach ($block['lessons'] as &$lesson) {
                $lesson['activity'] .= ' Synthetic support: explain one decision and verify it using the existing checklist.';
            }
            unset($lesson);

            return new ProviderResult(json_encode(['replacement' => $block, 'source_ids' => $input['source_ids'], 'reason' => 'Synthetic support proposal; validate against the approved evidence.'], JSON_THROW_ON_ERROR), 100, 200, 'mock-adaptation');
        }
        if (($input['task'] ?? '') === 'suggest_kpi_actions') {
            return new ProviderResult(json_encode(['source_ids' => $input['source_ids'], 'actions' => ['Review the approved evidence with the coordinator.', 'Practise the existing task within its allocated minutes.']], JSON_THROW_ON_ERROR), 100, 100, 'mock-kpi');
        }
        $number = $input['block_number'];
        $block = [
            'objective' => "Synthetic block {$number}: practise explaining a procedure and checking evidence.",
            'prerequisites' => $number === 1 ? 'Read the approved resource and identify a familiar task.' : 'Use the procedure and review checklist from the preceding block.',
            'prerequisite_block' => $number - 1, 'competency' => 1,
            'criterion_one' => 'The procedure identifies inputs, ordered actions and an observable result.',
            'criterion_two' => 'The review checklist records evidence for each step and explains corrections.',
            'weight_one' => 0.5, 'weight_two' => 0.5, 'lessons' => [],
        ];
        foreach ($input['dates'] as $index => $date) {
            $block['lessons'][] = [
                'date' => $date, 'title' => 'Synthetic practice '.($index + 1).": describe and verify a task (block {$number})",
                'explanation' => 'A reproducible procedure describes what is needed before work starts, the actions in order, and an observable result. Write one action per step. A checklist tests the result against evidence rather than assuming that completing an action guarantees success.',
                'example' => 'For a sample stock check: obtain the item list, count each item, compare each count with the list, record any difference, then ask a reviewer to verify one count. A completed count sheet and a recorded discrepancy provide evidence of the result.',
                'activity' => 'Using the approved resource, choose a synthetic task. Write its inputs, five ordered steps and expected result. Ask a peer to follow the steps, record a confusing instruction, and revise that instruction.',
                'tools' => 'Text editor and the coordinator-reviewed resource; use only synthetic examples.',
                'completion' => 'Produce the procedure, a checklist with evidence for each step, and a short explanation of one revision. Check both rubric criteria.',
                'reading' => 3, 'practice' => $input['daily_minutes'] - 9, 'assessment' => 3, 'revision' => 3,
                'kpi' => 1, 'resource_id' => $input['resource']['id'],
            ];
        }

        return new ProviderResult(json_encode($block, JSON_THROW_ON_ERROR), 100, 500, 'mock-block-'.$number);
    }
}
