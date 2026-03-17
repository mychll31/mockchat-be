<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignAssignment;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Leaderboard: all students ranked by overall average score.
     * Accessible to all authenticated users.
     */
    public function leaderboard(): JsonResponse
    {
        $students = User::where('role', 'student')
            ->where('enabled', true)
            ->get(['id', 'name', 'avatar']);

        $leaderboard = [];

        foreach ($students as $student) {
            // Ads training scores
            $adsScores = CampaignAssignment::where('student_id', $student->id)
                ->whereNotNull('score')
                ->pluck('score')
                ->toArray();

            // Chat training scores
            $chatScores = Conversation::where('user_id', $student->id)
                ->whereNotNull('mentor_score')
                ->pluck('mentor_score')
                ->toArray();

            $adsCount = CampaignAssignment::where('student_id', $student->id)->count();
            $chatCount = Conversation::where('user_id', $student->id)->count();

            $adsAvg = count($adsScores) ? round(array_sum($adsScores) / count($adsScores)) : null;
            $chatAvg = count($chatScores) ? round(array_sum($chatScores) / count($chatScores)) : null;

            $allScores = array_merge($adsScores, $chatScores);
            $overallAvg = count($allScores) ? round(array_sum($allScores) / count($allScores)) : null;

            $leaderboard[] = [
                'student_id' => $student->id,
                'name' => $student->name,
                'avatar' => $student->avatar,
                'ads_avg_score' => $adsAvg,
                'ads_graded' => count($adsScores),
                'ads_total' => $adsCount,
                'chat_avg_score' => $chatAvg,
                'chat_graded' => count($chatScores),
                'chat_total' => $chatCount,
                'overall_avg' => $overallAvg,
                'total_graded' => count($allScores),
            ];
        }

        // Sort by overall_avg descending (nulls last)
        usort($leaderboard, function ($a, $b) {
            if ($a['overall_avg'] === null && $b['overall_avg'] === null) return 0;
            if ($a['overall_avg'] === null) return 1;
            if ($b['overall_avg'] === null) return -1;
            return $b['overall_avg'] <=> $a['overall_avg'];
        });

        // Add rank
        foreach ($leaderboard as $i => &$entry) {
            $entry['rank'] = $i + 1;
        }

        return response()->json($leaderboard);
    }

    /**
     * Mentor/admin: detailed grades for all students.
     */
    public function allStudentGrades(): JsonResponse
    {
        $students = User::where('role', 'student')
            ->where('enabled', true)
            ->get(['id', 'name', 'email', 'avatar']);

        $result = [];

        foreach ($students as $student) {
            $adsAssignments = CampaignAssignment::where('student_id', $student->id)
                ->with('campaign:id,campaign_name')
                ->orderBy('created_at', 'desc')
                ->get(['id', 'campaign_id', 'decision', 'reasoning', 'decided_at', 'mentor_feedback', 'score', 'created_at']);

            $chatConversations = Conversation::where('user_id', $student->id)
                ->with('customerType:id,label')
                ->withCount('messages')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'customer_name' => $c->customer_name,
                    'customer_type' => $c->customerType?->label ?? 'Unknown',
                    'status' => $c->status,
                    'mentor_feedback' => $c->mentor_feedback,
                    'mentor_score' => $c->mentor_score,
                    'message_count' => $c->messages_count,
                    'created_at' => $c->created_at,
                ]);

            $adsScores = $adsAssignments->whereNotNull('score')->pluck('score')->toArray();
            $chatScores = $chatConversations->whereNotNull('mentor_score')->pluck('mentor_score')->toArray();
            $allScores = array_merge($adsScores, $chatScores);

            $result[] = [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'avatar' => $student->avatar,
                ],
                'ads_assignments' => $adsAssignments,
                'chat_conversations' => $chatConversations,
                'ads_avg' => count($adsScores) ? round(array_sum($adsScores) / count($adsScores)) : null,
                'chat_avg' => count($chatScores) ? round(array_sum($chatScores) / count($chatScores)) : null,
                'overall_avg' => count($allScores) ? round(array_sum($allScores) / count($allScores)) : null,
            ];
        }

        return response()->json($result);
    }
}
