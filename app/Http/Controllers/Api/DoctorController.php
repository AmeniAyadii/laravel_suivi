<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function getPhone(Request $request)
    {
        $name = $request->input('name');
        
        // Nettoyer le nom
        $cleanName = preg_replace('/^(Dr|Docteur|Dr\.)\s*/i', '', $name);
        $cleanName = trim($cleanName);
        
        $doctor = Doctor::where('nom', 'LIKE', "%{$cleanName}%")
            ->orWhere('prenom', 'LIKE', "%{$cleanName}%")
            ->first();
        
        if ($doctor) {
            return response()->json([
                'success' => true,
                'phone' => $doctor->cabinet_phone,
                'doctor' => $doctor
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Médecin non trouvé'
        ], 404);
    }
}