<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatusEnum;
use App\Models\Activity;
use App\Models\Booking;
use App\Events\BookingCreated;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Requests\UpdateBookingRequest;

class BookingController extends Controller
{
    /**
     * Display a listing of bookings
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        Gate::authorize('viewAny', Booking::class);

        // Get all bookings
        $bookings = Booking::with('activity')->get();

        return response()->json($bookings);
    }

    // Store a newly created booking in storage
    public function store(CreateBookingRequest $request)
    {
        Gate::authorize('create', Booking::class);

        // Find the activity being booked
        $activity = Activity::findOrFail($request->activity_id);

        // Check if enough slots are available
        if ($activity->available_slots < $request->slots_booked) {
            return response()->json(['error' => 'Not enough slots available'], 400);
        }

        // Create the booking
        $booking = Booking::create($request);

        // Reduce the available slots in the activity
        $activity->decrement('available_slots', $request->slots_booked);

        // Fire the event to handle email notifications
        event(new BookingCreated($booking));

        return response()->json($booking, 201);
    }

    // Display the specified booking
    public function show(Booking $booking)
    {
        Gate::authorize('view', $booking);

        return response()->json($booking);
    }

    // Update the specified booking in storage
    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        Gate::authorize('update', $booking);

        if ($booking->status !== BookingStatusEnum::Cancelled && $request->status === BookingStatusEnum::Cancelled->value) {
            $booking->activity->increment('available_slots', $booking->slots_booked);
        }
        if ($booking->status === BookingStatusEnum::Cancelled && $request->status !== BookingStatusEnum::Cancelled->value) {
            $booking->activity->decrement('available_slots', $booking->slots_booked);
        }

        $booking->update([...$request->all(), 'status' => BookingStatusEnum::tryFrom($request->status)]);

        return response()->json($booking);
    }

    // Remove the specified booking from storage
    public function destroy(Booking $booking)
    {
        Gate::authorize('delete', $booking);

        // Reduce the available slots in the activity
        if ($booking->status !== BookingStatusEnum::Cancelled) {
            $booking->activity->increment('available_slots', $booking->slots_booked);
        }

        $booking->delete();

        return response()->json(null, 204);
    }
}
