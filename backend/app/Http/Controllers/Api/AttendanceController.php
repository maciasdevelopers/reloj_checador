<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function checkIn(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = $request->user();
        $now = Carbon::now();

        // Verificar si ya tiene un check-in hoy sin check-out
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('check_in', $now->toDateString())
            ->whereNull('check_out')
            ->first();

        if ($attendance) {
            return response()->json(['message' => 'Ya has registrado tu entrada hoy.'], 400);
        }

        $distance = $this->calculateDistance(
            $request->latitude,
            $request->longitude
        );

        if ($distance > 100) {
            return response()->json([
                'message' => 'Estás fuera del rango permitido (100m). Distancia actual: ' . round($distance, 2) . 'm',
                'distance' => $distance
            ], 403);
        }

        Attendance::create([
            'user_id' => $user->id,
            'check_in' => $now,
            'latitude_in' => $request->latitude,
            'longitude_in' => $request->longitude,
            'distance_in' => $distance,
        ]);

        return response()->json(['message' => 'Entrada registrada correctamente.']);
    }

    public function checkOut(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = $request->user();
        $now = Carbon::now();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereNull('check_out')
            ->orderBy('check_in', 'desc')
            ->first();

        if (!$attendance) {
            return response()->json(['message' => 'No hay una entrada registrada para marcar salida.'], 400);
        }

        $distance = $this->calculateDistance(
            $request->latitude,
            $request->longitude
        );

        if ($distance > 100) {
            return response()->json([
                'message' => 'Estás fuera del rango permitido (100m). Distancia actual: ' . round($distance, 2) . 'm',
                'distance' => $distance
            ], 403);
        }

        $attendance->update([
            'check_out' => $now,
            'latitude_out' => $request->latitude,
            'longitude_out' => $request->longitude,
            'distance_out' => $distance,
        ]);

        return response()->json(['message' => 'Salida registrada correctamente.']);
    }

    public function status(Request $request)
    {
        $user = $request->user();
        $attendance = Attendance::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $schedule = Schedule::where('user_id', $user->id)
            ->where('day_of_week', strtolower(Carbon::now()->englishDayOfWeek))
            ->first();

        return response()->json([
            'last_attendance' => $attendance,
            'schedule' => $schedule,
            'is_checked_in' => $attendance && !$attendance->check_out,
            'company_location' => [
                'latitude' => CompanySetting::where('key', 'latitude')->value('value'),
                'longitude' => CompanySetting::where('key', 'longitude')->value('value'),
            ]
        ]);
    }

    private function calculateDistance($lat1, $lon1)
    {
        $companyLat = CompanySetting::where('key', 'latitude')->value('value') ?? 0;
        $companyLon = CompanySetting::where('key', 'longitude')->value('value') ?? 0;

        $earthRadius = 6371000; // metros

        $dLat = deg2rad($companyLat - $lat1);
        $dLon = deg2rad($companyLon - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($companyLat)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
