<?php
// app/Http/Controllers/ScanController.php

namespace App\Http\Controllers;

use App\Models\ScannedProduct;

use App\Services\ScanManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

use App\Services\NotificationService;


class ScanController extends Controller
{
    protected $scanManager;

    public function __construct(ScanManager $scanManager)
    {
        $this->scanManager = $scanManager;
    }

    /**
     * Scanner un code-barres
     */
    public function scan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $barcode = $request->barcode;

        try {
            // Rechercher le produit
            $product = $this->scanManager->search($barcode);

            // Vérifier si le produit existe déjà en base
            if (auth()->check()) {
                $existing = ScannedProduct::where('barcode', $barcode)
                    ->where('user_id', auth()->id())
                    ->first();

                if ($existing) {
                    $product['already_exists'] = true;
                    $product['existing_id'] = $existing->id;
                } else {
                    $product['already_exists'] = false;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $product,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur scan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du scan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enregistrer un produit scanné
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'barcode' => 'required|string|unique:scanned_products,barcode,NULL,id,user_id,' . $user->id,
                'nom' => 'required|string|max:255',
                'manufacturer' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:255',
                'sub_category' => 'nullable|string|max:255',
                'dosage' => 'nullable|string|max:100',
                'product_type' => 'nullable|string|max:50',
                'ingredients' => 'nullable|array',
                'indications' => 'nullable|array',
                'contre_indications' => 'nullable|array',
                'effets_secondaires' => 'nullable|array',
                'notes' => 'nullable|string',
                'expiry_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $product = ScannedProduct::create([
                'user_id' => $user->id,
                'barcode' => $request->barcode,
                'nom' => $request->nom,
                'manufacturer' => $request->manufacturer,
                'category' => $request->category,
                'sub_category' => $request->sub_category,
                'dosage' => $request->dosage,
                'product_type' => $request->product_type ?? 'unknown',
                'image_url' => $request->image_url,
                'ingredients' => $request->ingredients ?? [],
                'indications' => $request->indications ?? [],
                'contre_indications' => $request->contre_indications ?? [],
                'effets_secondaires' => $request->effets_secondaires ?? [],
                'notes' => $request->notes,
                'expiry_date' => $request->expiry_date,
                'source' => $request->source ?? 'Scan',
                'scanned_at' => now(),
            ]);

            // Vider le cache
            $this->scanManager->clearCache($request->barcode);

            return response()->json([
                'success' => true,
                'message' => 'Produit enregistré avec succès',
                'data' => $product,
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ Erreur store: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir l'historique des scans
     */
    public function history(Request $request)
    {
        try {
            $user = $request->user();

            $perPage = $request->input('per_page', 20);
            $page = $request->input('page', 1);

            $scans = ScannedProduct::where('user_id', $user->id)
                ->orderBy('scanned_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $scans->items(),
                'meta' => [
                    'total' => $scans->total(),
                    'current_page' => $scans->currentPage(),
                    'last_page' => $scans->lastPage(),
                    'per_page' => $scans->perPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur history: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement de l\'historique',
            ], 500);
        }
    }

    /**
     * Tester les APIs
     */
    public function test(Request $request)
    {
        $results = [
            'timestamp' => now()->toISOString(),
            'providers' => $this->scanManager->testProviders(),
        ];

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    // Ajouter un rappel pour le médicament
private function scheduleMedicationReminder($medication)
{
    $notificationService = app(NotificationService::class);
    $notificationService->sendToUser(
        auth()->user(),
        '💊 Rappel médicament',
        "Pensez à prendre " . $medication->nom . " (" . $medication->dosage . ")",
        [
            'type' => 'medication',
            'id' => (string) $medication->id,
        ]
    );
}
}