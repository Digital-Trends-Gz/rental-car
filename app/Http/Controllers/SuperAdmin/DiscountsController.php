<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscountsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Discounts/Index', [
            'discounts' => Discount::with('plan')->latest()->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Discounts/Create', [
            'plans' => Plan::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'required|boolean',
        ]);

        Discount::create($validated);

        return redirect()->route('superadmin.discounts.index')
            ->with('success', 'Discount created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Discount $discount): Response
    {
        return Inertia::render('SuperAdmin/Discounts/Edit', [
            'discount' => $discount,
            'plans' => Plan::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Discount $discount)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'required|boolean',
        ]);

        $discount->update($validated);

        return redirect()->route('superadmin.discounts.index')
            ->with('success', 'Discount updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Discount $discount)
    {
        $discount->delete();

        return redirect()->route('superadmin.discounts.index')
            ->with('success', 'Discount deleted successfully.');
    }
}
