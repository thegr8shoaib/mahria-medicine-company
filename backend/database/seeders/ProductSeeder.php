<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->wipeProductData();

        $products = $this->catalog();

        foreach ($products as $i => $row) {
            Product::create([
                'company' => $row[0],
                'name' => $row[1],
                'generic_name' => $row[2],
                'variants' => $row[3],
                'category' => $row[4],
                'sku' => $this->sku($row[1], $i),
                'barcode' => null,
                'price' => 0,
                'cost_price' => 0,
                'unit' => $this->unit($row[3]),
                'low_stock_alert' => 10,
                'is_active' => true,
            ]);
        }
    }

    private function wipeProductData(): void
    {
        DB::table('sale_items')->delete();
        DB::table('sales')->delete();
        DB::table('purchase_items')->delete();
        DB::table('purchases')->delete();
        DB::table('batches')->delete();
        DB::table('products')->delete();
    }

    private function sku(string $brand, int $index): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $brand));

        return $base . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
    }

    private function unit(string $variants): string
    {
        $v = strtolower($variants);

        if (str_contains($v, 'injection') || str_contains($v, 'infusion') || str_contains($v, 'iv')) {
            return 'injection';
        }
        if (str_contains($v, 'syrup') || str_contains($v, 'suspension') || str_contains($v, 'liquid')) {
            return 'syrup';
        }
        if (str_contains($v, 'drops')) {
            return 'drops';
        }
        if (str_contains($v, 'cream') || str_contains($v, 'ointment') || str_contains($v, 'gel')) {
            return 'ointment';
        }
        if (str_contains($v, 'capsule') || str_contains($v, 'softgel')) {
            return 'capsule';
        }
        if (str_contains($v, 'sachet') || str_contains($v, 'inhaler')) {
            return 'pack';
        }

        return 'tablet';
    }

    private function catalog(): array
    {
        return [
            ['GSK (GlaxoSmithKline)', 'Panadol', 'Paracetamol', 'Panadol 500mg, Panadol Extra, Panadol CF, Panadol Drops, Panadol Suspension', 'Analgesic / Antipyretic'],
            ['GSK (GlaxoSmithKline)', 'Augmentin', 'Co-amoxiclav (Amoxicillin + Clavulanic acid)', 'Augmentin 375mg, 625mg, 1g, DS Suspension, IV Injection', 'Antibiotic'],
            ['GSK (GlaxoSmithKline)', 'Velosef', 'Cephradine', 'Velosef 250mg, 500mg, Suspension, Injection', 'Antibiotic'],
            ['GSK (GlaxoSmithKline)', 'Betnovate', 'Betamethasone Valerate', 'Betnovate Cream, Ointment, Betnovate-N, Betnovate-C', 'Topical Corticosteroid'],
            ['GSK (GlaxoSmithKline)', 'Calpol', 'Paracetamol', 'Calpol Suspension, Calpol 6 Plus Suspension', 'Pediatric Analgesic'],
            ['Abbott Laboratories', 'Brufen', 'Ibuprofen', 'Brufen 200mg, 400mg, 600mg, Brufen DS Syrup', 'NSAID / Pain Relief'],
            ['Abbott Laboratories', 'Klaricid', 'Clarithromycin', 'Klaricid 250mg, 500mg, Klaricid XL, Suspension', 'Macrolide Antibiotic'],
            ['Abbott Laboratories', 'Arinac', 'Ibuprofen + Pseudoephedrine', 'Arinac Tablets, Arinac Forte, Arinac Syrup', 'Cold & Allergy Relief'],
            ['Abbott Laboratories', 'Surbex', 'Multivitamins + Zinc', 'Surbex-T, Surbex Z', 'Nutritional Supplement'],
            ['Abbott Laboratories', 'Hydryllin', 'Aminophylline + Diphenhydramine', 'Hydryllin Syrup, Hydryllin DM Syrup', 'Cough Expectorant'],
            ['Getz Pharma', 'Risek', 'Omeprazole', 'Risek 20mg, 40mg Capsules, Risek Insta Sachet, Risek IV Injection', 'Proton Pump Inhibitor (PPI)'],
            ['Getz Pharma', 'Getryl', 'Glimepiride', 'Getryl 1mg, 2mg, 3mg, 4mg', 'Anti-diabetic'],
            ['Getz Pharma', 'Lipiget', 'Atorvastatin', 'Lipiget 10mg, 20mg, 40mg', 'Statin / Lipid Lowering'],
            ['Getz Pharma', 'Eziday', 'Losartan Potassium', 'Eziday 25mg, 50mg, 100mg, Eziday Co', 'Antihypertensive'],
            ['Getz Pharma', 'Montika', 'Montelukast', 'Montika 4mg, 5mg Chewable, 10mg Tablets', 'Anti-asthmatic / Leukotriene Inhibitor'],
            ['Searle Company', 'Gravinate', 'Dimenhydrinate', 'Gravinate 50mg Tablets, Syrup, Injection', 'Antiemetic / Motion Sickness'],
            ['Searle Company', 'Nuberol Forte', 'Paracetamol + Orphenadrine', 'Nuberol Forte Tablets', 'Muscle Relaxant / Painkiller'],
            ['Searle Company', 'Extacef', 'Cefixime', 'Extacef 200mg, 400mg, Suspension', 'Cephalosporin Antibiotic'],
            ['Searle Company', 'Peditral', 'Oral Rehydration Salts (ORS)', 'Peditral Liquid (Various flavors)', 'Electrolyte Replenishment'],
            ['Highnoon Laboratories', 'Combivair', 'Formoterol + Budesonide', 'Combivair Inhaler, Rotocaps', 'Bronchodilator / Asthma Care'],
            ['Highnoon Laboratories', 'Kestine', 'Ebastine', 'Kestine 10mg, 20mg, Kestine Syrup', 'Antihistamine'],
            ['Highnoon Laboratories', 'Teejel', 'Choline Salicylate + Cetalkonium', 'Teejel Gel', 'Oral Analgesic Gel'],
            ['Highnoon Laboratories', 'Tres Orix Forte', 'Cyproheptadine + Orotate + Vitamins', 'Tres Orix Forte Syrup, Capsules', 'Appetite Stimulant'],
            ['Sami Pharmaceuticals', 'Loprin', 'Aspirin (Acetylsalicylic Acid)', 'Loprin 75mg, Loprin 150mg', 'Antiplatelet / Blood Thinner'],
            ['Sami Pharmaceuticals', 'Macrodantin', 'Nitrofurantoin', 'Macrodantin 50mg, 100mg', 'Urinary Tract Antiseptic'],
            ['Sami Pharmaceuticals', 'Zidon', 'Cetirizine HCI', 'Zidon 10mg, Zidon Syrup', 'Antihistamine'],
            ['Hilton Pharma', 'Novidat', 'Ciprofloxacin', 'Novidat 250mg, 500mg, Infusion', 'Fluoroquinolone Antibiotic'],
            ['Hilton Pharma', 'Gravid', 'Doxylamine Succinate + Pyridoxine HCI', 'Gravid Tablets', 'Anti-morning Sickness'],
            ['CCL Pharmaceuticals', 'Pulmonol', 'Guaifenesin + Ammonium Chloride', 'Pulmonol Syrup, Pulmonol S', 'Cough Mixture'],
            ['CCL Pharmaceuticals', 'Cranmax', 'Cranberry Extract', 'Cranmax Sachets, Capsules', 'Urinary Tract Support'],
            ['Efroze Chemical', 'Efrozone', 'Ceftriaxone', 'Efrozone 250mg, 500mg, 1g Injection', 'Antibiotic'],
            ['Efroze Chemical', 'E-Core', 'Vitamin E (Alpha Tocopheryl)', 'E-Core 200mg, 400mg Capsules', 'Nutritional Antioxidant'],
            ['LCI (ICI Pakistan)', 'Tenormin', 'Atenolol', 'Tenormin 25mg, 50mg, 100mg', 'Beta-Blocker / Antihypertensive'],
            ['Barrett Hodgson', 'Isoptin', 'Verapamil HCI', 'Isoptin 40mg, 80mg, Isoptin SR 240mg', 'Calcium Channel Blocker'],
            ['Nabiqasim Pharma', 'Qalsan D', 'Calcium Carbonate + Vitamin D3', 'Qalsan D Chewable Tablets', 'Bone Mineral Supplement'],
            ['Genix Pharma', 'Genurin', 'Flavoxate HCI', 'Genurin 200mg Tablets', 'Urinary Antispasmodic'],
            ['PharmEvo', 'Evocal', 'Calcium + Vitamin D3', 'Evocal Chewable Tablets', 'Bone Health Supplement'],
            ['OBS Pakistan', 'Evion', 'Vitamin E', 'Evion 200mg, 400mg, 600mg Softgels', 'Antioxidant Supplement'],
            ['Opal Laboratories', 'Opalgin', 'Metamizole / Dipyrone', 'Opalgin Tablets, Syrup', 'Analgesic / Antipyretic'],
            ['Amson Vaccines', 'Amsocillin', 'Amoxicillin', 'Amsocillin 250mg, 500mg, Suspension', 'Antibiotic'],
            ['Sante', 'Sante Tears', 'Carboxymethylcellulose', 'Sante Tears Eye Drops', 'Ophthalmic Lubricant'],
            ['High Q Pharmaceuticals', 'Q-Ceph', 'Cephradine', 'Q-Ceph 250mg, 500mg Capsules, Suspension', 'Antibiotic'],
        ];
    }
}