<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignAssignment;
use App\Models\CampaignGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    // ─── Admin/Mentor: Campaign CRUD ───

    public function index(Request $request): JsonResponse
    {
        $query = Campaign::where('created_by', $request->user()->id);

        if ($request->has('search')) {
            $query->where('campaign_name', 'like', '%' . $request->search . '%');
        }

        $campaigns = $query->orderBy('created_at', 'desc')->get();
        return response()->json($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_name' => 'required|string|max:255',
            'status' => 'in:Off,On',
            'delivery' => 'in:Off,Active,Learning',
            'results' => 'nullable|integer',
            'result_type' => 'nullable|string|max:255',
            'cost_per_result' => 'nullable|numeric|min:0',
            'cost_per_result_type' => 'nullable|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'budget_type' => 'nullable|string|max:255',
            'amount_spent' => 'nullable|numeric|min:0',
            'impressions' => 'nullable|integer|min:0',
            'reach' => 'nullable|integer|min:0',
            'ends' => 'nullable|string|max:255',
            'attribution_setting' => 'nullable|string|max:255',
            'bid_strategy' => 'nullable|string|max:255',
            'total_messaging' => 'nullable|integer|min:0',
            'new_messaging' => 'nullable|integer|min:0',
            'purchases' => 'nullable|integer|min:0',
            'cost_per_purchase' => 'nullable|numeric|min:0',
            'purchases_conversion_value' => 'nullable|numeric|min:0',
            'purchase_roas' => 'nullable|numeric|min:0',
            'cost_per_new_messaging' => 'nullable|numeric|min:0',
            'messaging_conversations' => 'nullable|integer|min:0',
            'cost_per_messaging' => 'nullable|numeric|min:0',
            'orders_created' => 'nullable|integer|min:0',
            'orders_shipped' => 'nullable|integer|min:0',
            'date_range_start' => 'nullable|date',
            'date_range_end' => 'nullable|date|after_or_equal:date_range_start',
        ]);

        $validated['created_by'] = $request->user()->id;
        $campaign = Campaign::create($validated);

        return response()->json($campaign, 201);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        return response()->json($campaign);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'campaign_name' => 'sometimes|required|string|max:255',
            'status' => 'in:Off,On',
            'delivery' => 'in:Off,Active,Learning',
            'results' => 'nullable|integer',
            'result_type' => 'nullable|string|max:255',
            'cost_per_result' => 'nullable|numeric|min:0',
            'cost_per_result_type' => 'nullable|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'budget_type' => 'nullable|string|max:255',
            'amount_spent' => 'nullable|numeric|min:0',
            'impressions' => 'nullable|integer|min:0',
            'reach' => 'nullable|integer|min:0',
            'ends' => 'nullable|string|max:255',
            'attribution_setting' => 'nullable|string|max:255',
            'bid_strategy' => 'nullable|string|max:255',
            'total_messaging' => 'nullable|integer|min:0',
            'new_messaging' => 'nullable|integer|min:0',
            'purchases' => 'nullable|integer|min:0',
            'cost_per_purchase' => 'nullable|numeric|min:0',
            'purchases_conversion_value' => 'nullable|numeric|min:0',
            'purchase_roas' => 'nullable|numeric|min:0',
            'cost_per_new_messaging' => 'nullable|numeric|min:0',
            'messaging_conversations' => 'nullable|integer|min:0',
            'cost_per_messaging' => 'nullable|numeric|min:0',
            'orders_created' => 'nullable|integer|min:0',
            'orders_shipped' => 'nullable|integer|min:0',
            'date_range_start' => 'nullable|date',
            'date_range_end' => 'nullable|date|after_or_equal:date_range_start',
        ]);

        $campaign->update($validated);
        return response()->json($campaign);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $campaign->delete();
        return response()->json(['message' => 'Campaign deleted']);
    }

    public function duplicate(Campaign $campaign): JsonResponse
    {
        $clone = $campaign->replicate();
        $clone->campaign_name = $campaign->campaign_name . ' (Copy)';
        $clone->save();

        return response()->json($clone, 201);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_shift($rows);
        $header = array_map('trim', $header);

        $campaigns = [];
        foreach ($rows as $row) {
            if (count($row) !== count($header)) continue;
            $data = array_combine($header, $row);
            $data['created_by'] = $request->user()->id;

            // Convert empty strings to null
            foreach ($data as $key => $value) {
                if ($value === '') $data[$key] = null;
            }

            $campaigns[] = Campaign::create($data);
        }

        return response()->json([
            'message' => count($campaigns) . ' campaigns imported',
            'campaigns' => $campaigns,
        ], 201);
    }

    // ─── Admin/Mentor: Campaign Groups ───

    public function groupIndex(Request $request): JsonResponse
    {
        $groups = CampaignGroup::where('created_by', $request->user()->id)
            ->with('campaigns')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($groups);
    }

    public function groupStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $validated['created_by'] = $request->user()->id;
        $group = CampaignGroup::create($validated);

        return response()->json($group, 201);
    }

    public function groupUpdate(Request $request, CampaignGroup $campaignGroup): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $campaignGroup->update($validated);
        return response()->json($campaignGroup);
    }

    public function groupDestroy(CampaignGroup $campaignGroup): JsonResponse
    {
        $campaignGroup->delete();
        return response()->json(['message' => 'Campaign group deleted']);
    }

    public function groupAddCampaigns(Request $request, CampaignGroup $campaignGroup): JsonResponse
    {
        $validated = $request->validate([
            'campaign_ids' => 'required|array',
            'campaign_ids.*' => 'exists:campaigns,id',
        ]);

        $maxSort = $campaignGroup->campaigns()->max('campaign_group_items.sort_order') ?? -1;

        foreach ($validated['campaign_ids'] as $i => $campaignId) {
            $campaignGroup->campaigns()->syncWithoutDetaching([
                $campaignId => ['sort_order' => $maxSort + $i + 1],
            ]);
        }

        return response()->json($campaignGroup->load('campaigns'));
    }

    // ─── Admin/Mentor: Assignments ───

    public function assignmentStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_ids' => 'required|array',
            'campaign_ids.*' => 'exists:campaigns,id',
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:users,id',
        ]);

        $created = [];
        foreach ($validated['campaign_ids'] as $campaignId) {
            foreach ($validated['student_ids'] as $studentId) {
                $assignment = CampaignAssignment::firstOrCreate([
                    'campaign_id' => $campaignId,
                    'student_id' => $studentId,
                ]);
                $created[] = $assignment;
            }
        }

        return response()->json([
            'message' => count($created) . ' assignments created',
            'assignments' => $created,
        ], 201);
    }

    public function assignmentIndex(Request $request): JsonResponse
    {
        $query = CampaignAssignment::with(['campaign', 'student']);

        // Filter: only show assignments for campaigns created by this mentor
        if ($request->user()->role !== 'admin') {
            $query->whereHas('campaign', function ($q) use ($request) {
                $q->where('created_by', $request->user()->id);
            });
        }

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('decision')) {
            if ($request->decision === 'pending') {
                $query->whereNull('decision');
            } else {
                $query->where('decision', $request->decision);
            }
        }

        $assignments = $query->orderBy('created_at', 'desc')->get();
        return response()->json($assignments);
    }

    public function assignmentFeedback(Request $request, CampaignAssignment $assignment): JsonResponse
    {
        $validated = $request->validate([
            'mentor_feedback' => 'nullable|string',
            'score' => 'nullable|integer|min:0|max:100',
        ]);

        $assignment->update($validated);
        return response()->json($assignment);
    }

    // ─── Student: View & Decide ───

    public function studentAssignments(Request $request): JsonResponse
    {
        $assignments = CampaignAssignment::where('student_id', $request->user()->id)
            ->with('campaign')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($assignments);
    }

    public function studentAssignmentShow(Request $request, CampaignAssignment $assignment): JsonResponse
    {
        if ($assignment->student_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($assignment->load('campaign'));
    }

    public function studentDecide(Request $request, CampaignAssignment $assignment): JsonResponse
    {
        if ($assignment->student_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($assignment->decision !== null) {
            return response()->json(['message' => 'Decision already submitted'], 422);
        }

        $validated = $request->validate([
            'decision' => 'required|in:stop,continue',
            'reasoning' => 'required|string|min:50',
        ]);

        $assignment->update([
            'decision' => $validated['decision'],
            'reasoning' => $validated['reasoning'],
            'decided_at' => now(),
        ]);

        return response()->json($assignment->load('campaign'));
    }
}
