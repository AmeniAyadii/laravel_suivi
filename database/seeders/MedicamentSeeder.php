<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Medicament;
use App\Models\User;

class MedicamentSeeder extends Seeder
{
    public function run()
    {
        // Récupérer le premier utilisateur
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'nom' => 'Admin',
                'age' => 30,
                'sexe' => 'M',
                'taille' => 175,
                'poids' => 70,
                'groupe_sanguin' => 'A+',
                'allergies' => 'Aucune',
                'maladies_chroniques' => 'Aucune',
                'email' => 'admin@test.com',
                'password' => bcrypt('password123')
            ]);
        }

        // ==================== LISTE DES MÉDICAMENTS TUNISIE ====================
        $medicaments = [
            // ==================== ANTALGIQUES / ANTIPYRÉTIQUES ====================
            [
                'barcode' => '6192408100810',
                'nom' => 'DOLIPRANE 1000mg',
                'manufacturer' => 'Winthrop Pharma Tunisie',
                'category' => 'Antalgique/Antipyrétique',
                'dosage' => '1000mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100827',
                'nom' => 'DOLIPRANE 500mg',
                'manufacturer' => 'Winthrop Pharma Tunisie',
                'category' => 'Antalgique/Antipyrétique',
                'dosage' => '500mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100834',
                'nom' => 'DOLIPRANE SIROP 2%',
                'manufacturer' => 'Winthrop Pharma Tunisie',
                'category' => 'Antalgique/Antipyrétique',
                'dosage' => '2%',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100841',
                'nom' => 'DOLIPRANE PRO 1000mg',
                'manufacturer' => 'Winthrop Pharma Tunisie',
                'category' => 'Antalgique/Antipyrétique',
                'dosage' => '1000mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100858',
                'nom' => 'DOLIPRANE VITAMINE C',
                'manufacturer' => 'Winthrop Pharma Tunisie',
                'category' => 'Antalgique/Antipyrétique',
                'dosage' => '500mg + Vit C',
                'statut' => 'actif'
            ],

            // ==================== ANTI-INFLAMMATOIRES ====================
            [
                'barcode' => '6192408100865',
                'nom' => 'IBUPROFENE 400mg',
                'manufacturer' => 'Mylan Tunisie',
                'category' => 'Anti-inflammatoire',
                'dosage' => '400mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100872',
                'nom' => 'IBUPROFENE 600mg',
                'manufacturer' => 'Mylan Tunisie',
                'category' => 'Anti-inflammatoire',
                'dosage' => '600mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100889',
                'nom' => 'ADVIL 400mg',
                'manufacturer' => 'Pfizer Tunisie',
                'category' => 'Anti-inflammatoire',
                'dosage' => '400mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100896',
                'nom' => 'ADVIL 600mg',
                'manufacturer' => 'Pfizer Tunisie',
                'category' => 'Anti-inflammatoire',
                'dosage' => '600mg',
                'statut' => 'actif'
            ],

            // ==================== SPASMOLYTIQUES ====================
            [
                'barcode' => '6192408100902',
                'nom' => 'SPASFON 80mg',
                'manufacturer' => 'Teva Tunisie',
                'category' => 'Antispasmodique',
                'dosage' => '80mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100919',
                'nom' => 'SPASFON LYOC 80mg',
                'manufacturer' => 'Teva Tunisie',
                'category' => 'Antispasmodique',
                'dosage' => '80mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100926',
                'nom' => 'BUSCOPAN 10mg',
                'manufacturer' => 'Boehringer Ingelheim Tunisie',
                'category' => 'Antispasmodique',
                'dosage' => '10mg',
                'statut' => 'actif'
            ],

            // ==================== ANTIBIOTIQUES ====================
            [
                'barcode' => '6192408100933',
                'nom' => 'AMOXICILLINE 500mg',
                'manufacturer' => 'Mylan Tunisie',
                'category' => 'Antibiotique',
                'dosage' => '500mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100940',
                'nom' => 'AMOXICILLINE 1g',
                'manufacturer' => 'Mylan Tunisie',
                'category' => 'Antibiotique',
                'dosage' => '1g',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100957',
                'nom' => 'AUGMENTIN 500mg',
                'manufacturer' => 'GSK Tunisie',
                'category' => 'Antibiotique',
                'dosage' => '500mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100964',
                'nom' => 'AUGMENTIN 1g',
                'manufacturer' => 'GSK Tunisie',
                'category' => 'Antibiotique',
                'dosage' => '1g',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100971',
                'nom' => 'AZITHROMYCINE 250mg',
                'manufacturer' => 'Pfizer Tunisie',
                'category' => 'Antibiotique',
                'dosage' => '250mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408100988',
                'nom' => 'AZITHROMYCINE 500mg',
                'manufacturer' => 'Pfizer Tunisie',
                'category' => 'Antibiotique',
                'dosage' => '500mg',
                'statut' => 'actif'
            ],

            // ==================== TRAITEMENTS RESPIRATOIRES ====================
            [
                'barcode' => '6192408100995',
                'nom' => 'VENTOLINE 100µg',
                'manufacturer' => 'GSK Tunisie',
                'category' => 'Bronchodilatateur',
                'dosage' => '100µg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101008',
                'nom' => 'VENTOLINE 200µg',
                'manufacturer' => 'GSK Tunisie',
                'category' => 'Bronchodilatateur',
                'dosage' => '200µg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101015',
                'nom' => 'SERETIDE 50/250',
                'manufacturer' => 'GSK Tunisie',
                'category' => 'Bronchodilatateur',
                'dosage' => '50/250µg',
                'statut' => 'actif'
            ],

            // ==================== TRAITEMENTS CARDIOVASCULAIRES ====================
            [
                'barcode' => '6192408101022',
                'nom' => 'ASPIRINE 100mg',
                'manufacturer' => 'Bayer Tunisie',
                'category' => 'Antiagrégant',
                'dosage' => '100mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101039',
                'nom' => 'ASPIRINE 300mg',
                'manufacturer' => 'Bayer Tunisie',
                'category' => 'Antiagrégant',
                'dosage' => '300mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101046',
                'nom' => 'STATINE 20mg',
                'manufacturer' => 'Pfizer Tunisie',
                'category' => 'Hypolipémiant',
                'dosage' => '20mg',
                'statut' => 'actif'
            ],

            // ==================== ANTIHISTAMINIQUES ====================
            [
                'barcode' => '6192408101053',
                'nom' => 'ZYRTEC 10mg',
                'manufacturer' => 'UCB Tunisie',
                'category' => 'Antihistaminique',
                'dosage' => '10mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101060',
                'nom' => 'ZYRTEC SIROP',
                'manufacturer' => 'UCB Tunisie',
                'category' => 'Antihistaminique',
                'dosage' => '1mg/ml',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101077',
                'nom' => 'CLARITYNE 10mg',
                'manufacturer' => 'Bayer Tunisie',
                'category' => 'Antihistaminique',
                'dosage' => '10mg',
                'statut' => 'actif'
            ],

            // ==================== GASTRO-ENTÉROLOGIE ====================
            [
                'barcode' => '6192408101084',
                'nom' => 'IMMODIUM 2mg',
                'manufacturer' => 'Janssen Tunisie',
                'category' => 'Antidiarrhéique',
                'dosage' => '2mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101091',
                'nom' => 'OMEPRAZOLE 20mg',
                'manufacturer' => 'Mylan Tunisie',
                'category' => 'Antiulcéreux',
                'dosage' => '20mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101107',
                'nom' => 'PANTOPRAZOLE 40mg',
                'manufacturer' => 'Mylan Tunisie',
                'category' => 'Antiulcéreux',
                'dosage' => '40mg',
                'statut' => 'actif'
            ],

            // ==================== DIABÈTE ====================
            [
                'barcode' => '6192408101114',
                'nom' => 'METFORMINE 500mg',
                'manufacturer' => 'Mylan Tunisie',
                'category' => 'Antidiabétique',
                'dosage' => '500mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101121',
                'nom' => 'METFORMINE 1000mg',
                'manufacturer' => 'Mylan Tunisie',
                'category' => 'Antidiabétique',
                'dosage' => '1000mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101138',
                'nom' => 'GLUCOPHAGE 500mg',
                'manufacturer' => 'Merck Tunisie',
                'category' => 'Antidiabétique',
                'dosage' => '500mg',
                'statut' => 'actif'
            ],

            // ==================== VITAMINES ET MINÉRAUX ====================
            [
                'barcode' => '6192408101145',
                'nom' => 'VITAMINE C 500mg',
                'manufacturer' => 'Bayer Tunisie',
                'category' => 'Vitamine',
                'dosage' => '500mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101152',
                'nom' => 'VITAMINE D 1000UI',
                'manufacturer' => 'Mylan Tunisie',
                'category' => 'Vitamine',
                'dosage' => '1000UI',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101169',
                'nom' => 'CALCIUM 500mg',
                'manufacturer' => 'Mylan Tunisie',
                'category' => 'Minéral',
                'dosage' => '500mg',
                'statut' => 'actif'
            ],

            // ==================== AUTRES MÉDICAMENTS COURANTS ====================
            [
                'barcode' => '6192408101176',
                'nom' => 'HEMOROIDAL CREAM',
                'manufacturer' => 'Pierre Fabre Tunisie',
                'category' => 'Dermatologique',
                'dosage' => '30g',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101183',
                'nom' => 'ZOVIRAX 200mg',
                'manufacturer' => 'GSK Tunisie',
                'category' => 'Antiviral',
                'dosage' => '200mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101190',
                'nom' => 'ZOVIRAX 400mg',
                'manufacturer' => 'GSK Tunisie',
                'category' => 'Antiviral',
                'dosage' => '400mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101206',
                'nom' => 'TYLENOL 500mg',
                'manufacturer' => 'McNeil Tunisie',
                'category' => 'Antalgique/Antipyrétique',
                'dosage' => '500mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101213',
                'nom' => 'TYLENOL 1000mg',
                'manufacturer' => 'McNeil Tunisie',
                'category' => 'Antalgique/Antipyrétique',
                'dosage' => '1000mg',
                'statut' => 'actif'
            ],
            [
                'barcode' => '6192408101220',
                'nom' => 'MADOL 500mg',
                'manufacturer' => 'Pharma Tunisie',
                'category' => 'Antalgique/Antipyrétique',
                'dosage' => '500mg',
                'statut' => 'actif'
            ],
        ];

        // ==================== INSERTION DANS LA BASE ====================
        $count = 0;
        foreach ($medicaments as $medicament) {
            // Vérifier si le médicament existe déjà pour cet utilisateur
            $existing = Medicament::where('barcode', $medicament['barcode'])
                ->where('user_id', $user->id)
                ->first();

            if (!$existing) {
                Medicament::create([
                    'user_id' => $user->id,
                    'barcode' => $medicament['barcode'],
                    'code_type' => 'EAN-13',
                    'nom' => $medicament['nom'],
                    'manufacturer' => $medicament['manufacturer'],
                    'category' => $medicament['category'],
                    'dosage' => $medicament['dosage'],
                    'statut' => $medicament['statut'],
                    'scanned' => false
                ]);
                $count++;
            }
        }

        $this->command->info("✅ $count médicaments tunisiens ajoutés avec succès !");
    }
}