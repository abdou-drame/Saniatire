<?php

namespace Database\Seeders;

use App\Domain\Icd\Models\IcdCode;
use App\Domain\Icd\Models\IcdCodeMapping;
use Illuminate\Database\Seeder;

/**
 * Restricted but real dataset (~30 CIM-10 codes + their CIM-11 equivalents)
 * covering a variety of common conditions, with genuine WHO chapter/group
 * hierarchy — enough for the coding engine to work end-to-end without
 * importing the full official referentiel. See README-ICD.md for how to
 * import it later.
 */
class IcdCodeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCim10();
        $this->seedCim11();
        $this->seedMappings();
    }

    private function seedCim10(): void
    {
        // Chapter I — Certain infectious and parasitic diseases
        $chapI = $this->code('CIM-10', 'I', 'Certaines maladies infectieuses et parasitaires', null, 'chapitre');
        $groupA00A09 = $this->code('CIM-10', 'A00-A09', 'Maladies infectieuses intestinales', $chapI->id, 'groupe');
        $this->code('CIM-10', 'A09', 'Diarrhée et gastro-entérite d\'origine infectieuse présumée', $groupA00A09->id);
        $groupA15A19 = $this->code('CIM-10', 'A15-A19', 'Tuberculose', $chapI->id, 'groupe');
        $this->code('CIM-10', 'A15', 'Tuberculose respiratoire, confirmée bactériologiquement', $groupA15A19->id);
        $groupB50B64 = $this->code('CIM-10', 'B50-B64', 'Maladies dues à des protozoaires', $chapI->id, 'groupe');
        $this->code('CIM-10', 'B54', 'Paludisme, sans précision', $groupB50B64->id);

        // Chapter IV — Endocrine, nutritional and metabolic diseases
        $chapIV = $this->code('CIM-10', 'IV', 'Maladies endocriniennes, nutritionnelles et métaboliques', null, 'chapitre');
        $groupE10E14 = $this->code('CIM-10', 'E10-E14', 'Diabète sucré', $chapIV->id, 'groupe');
        $this->code('CIM-10', 'E10', 'Diabète sucré de type 1', $groupE10E14->id);
        $this->code('CIM-10', 'E11', 'Diabète sucré de type 2', $groupE10E14->id);
        $groupE65E68 = $this->code('CIM-10', 'E65-E68', 'Surcharge pondérale et autres excès d\'apport', $chapIV->id, 'groupe');
        $this->code('CIM-10', 'E66', 'Obésité', $groupE65E68->id);

        // Chapter IX — Diseases of the circulatory system
        $chapIX = $this->code('CIM-10', 'IX', 'Maladies de l\'appareil circulatoire', null, 'chapitre');
        $groupI10I15 = $this->code('CIM-10', 'I10-I15', 'Maladies hypertensives', $chapIX->id, 'groupe');
        $this->code('CIM-10', 'I10', 'Hypertension essentielle (primitive)', $groupI10I15->id);
        $groupI20I25 = $this->code('CIM-10', 'I20-I25', 'Cardiopathies ischémiques', $chapIX->id, 'groupe');
        $this->code('CIM-10', 'I21', 'Infarctus aigu du myocarde', $groupI20I25->id);

        // Chapter X — Diseases of the respiratory system
        $chapX = $this->code('CIM-10', 'X', 'Maladies de l\'appareil respiratoire', null, 'chapitre');
        $groupJ00J06 = $this->code('CIM-10', 'J00-J06', 'Infections aiguës des voies respiratoires supérieures', $chapX->id, 'groupe');
        $this->code('CIM-10', 'J00', 'Rhinopharyngite aiguë (rhume banal)', $groupJ00J06->id);
        $groupJ09J18 = $this->code('CIM-10', 'J09-J18', 'Grippe et pneumopathie', $chapX->id, 'groupe');
        $this->code('CIM-10', 'J11', 'Grippe, virus non identifié', $groupJ09J18->id);
        $this->code('CIM-10', 'J18', 'Pneumopathie, organisme non précisé', $groupJ09J18->id);
        $groupJ40J47 = $this->code('CIM-10', 'J40-J47', 'Maladies chroniques des voies respiratoires inférieures', $chapX->id, 'groupe');
        $this->code('CIM-10', 'J45', 'Asthme', $groupJ40J47->id);

        // Chapter XI — Diseases of the digestive system
        $chapXI = $this->code('CIM-10', 'XI', 'Maladies de l\'appareil digestif', null, 'chapitre');
        $groupK25K28 = $this->code('CIM-10', 'K25-K28', 'Ulcère', $chapXI->id, 'groupe');
        $this->code('CIM-10', 'K25', 'Ulcère de l\'estomac', $groupK25K28->id);
        $groupK35K38 = $this->code('CIM-10', 'K35-K38', 'Maladies de l\'appendice', $chapXI->id, 'groupe');
        $this->code('CIM-10', 'K35', 'Appendicite aiguë', $groupK35K38->id);

        // Chapter XIII — Diseases of the musculoskeletal system
        $chapXIII = $this->code('CIM-10', 'XIII', 'Maladies du système ostéo-articulaire, des muscles et du tissu conjonctif', null, 'chapitre');
        $groupM15M19 = $this->code('CIM-10', 'M15-M19', 'Arthrose', $chapXIII->id, 'groupe');
        $this->code('CIM-10', 'M17', 'Gonarthrose (arthrose du genou)', $groupM15M19->id);
        $groupM54 = $this->code('CIM-10', 'M54', 'Dorsalgie', $chapXIII->id, 'groupe');
        $this->code('CIM-10', 'M54.5', 'Lombalgie basse', $groupM54->id);

        // Chapter XIX — Injury, poisoning and certain other consequences of external causes
        $chapXIX = $this->code('CIM-10', 'XIX', 'Lésions traumatiques, empoisonnements et certaines autres conséquences de causes externes', null, 'chapitre');
        $groupS52 = $this->code('CIM-10', 'S52', 'Fracture de l\'avant-bras', $chapXIX->id, 'groupe');
        $this->code('CIM-10', 'S52.5', 'Fracture de l\'extrémité inférieure du radius', $groupS52->id);
        $groupS72 = $this->code('CIM-10', 'S72', 'Fracture du fémur', $chapXIX->id, 'groupe');
        $this->code('CIM-10', 'S72.0', 'Fracture du col du fémur', $groupS72->id);

        // Chapter XV — Pregnancy, childbirth and the puerperium
        $chapXV = $this->code('CIM-10', 'XV', 'Grossesse, accouchement et puerpéralité', null, 'chapitre');
        $groupO20O29 = $this->code('CIM-10', 'O20-O29', 'Autres affections maternelles liées à la grossesse', $chapXV->id, 'groupe');
        $this->code('CIM-10', 'O23', 'Infection des voies génito-urinaires au cours de la grossesse', $groupO20O29->id);
    }

    private function seedCim11(): void
    {
        $chap1 = $this->code('CIM-11', '01', 'Certaines maladies infectieuses ou parasitaires', null, 'chapitre');
        $this->code('CIM-11', '1D40', 'Paludisme dû à Plasmodium falciparum', $chap1->id);
        $this->code('CIM-11', '1B10', 'Tuberculose des poumons', $chap1->id);

        $chap5 = $this->code('CIM-11', '05', 'Maladies endocriniennes, nutritionnelles ou métaboliques', null, 'chapitre');
        $this->code('CIM-11', '5A10', 'Diabète sucré de type 1', $chap5->id);
        $this->code('CIM-11', '5A11', 'Diabète sucré de type 2', $chap5->id);
        $this->code('CIM-11', '5B81', 'Obésité', $chap5->id);

        $chap11 = $this->code('CIM-11', '11', 'Maladies de l\'appareil circulatoire', null, 'chapitre');
        $this->code('CIM-11', 'BA00', 'Hypertension essentielle', $chap11->id);
        $this->code('CIM-11', 'BA41', 'Infarctus aigu du myocarde', $chap11->id);

        $chap12 = $this->code('CIM-11', '12', 'Maladies de l\'appareil respiratoire', null, 'chapitre');
        $this->code('CIM-11', 'CA03', 'Rhinopharyngite aiguë', $chap12->id);
        $this->code('CIM-11', 'CA40', 'Grippe due à un virus identifié', $chap12->id);
        $this->code('CIM-11', 'CA23', 'Asthme', $chap12->id);

        $chap13 = $this->code('CIM-11', '13', 'Maladies de l\'appareil digestif', null, 'chapitre');
        $this->code('CIM-11', 'DA60', 'Ulcère gastrique', $chap13->id);
        $this->code('CIM-11', 'DB30', 'Appendicite aiguë', $chap13->id);

        $chap15 = $this->code('CIM-11', '15', 'Maladies du système ostéo-articulaire ou des tissus conjonctifs', null, 'chapitre');
        $this->code('CIM-11', 'FA05', 'Gonarthrose', $chap15->id);
        $this->code('CIM-11', 'ME84.2', 'Lombalgie basse', $chap15->id);

        $chap22 = $this->code('CIM-11', '22', 'Lésions traumatiques, empoisonnements ou certaines autres conséquences de causes externes', null, 'chapitre');
        $this->code('CIM-11', 'NC72', 'Fracture de l\'extrémité inférieure du radius', $chap22->id);
        $this->code('CIM-11', 'NC93', 'Fracture du col du fémur', $chap22->id);

        $chap18 = $this->code('CIM-11', '18', 'Grossesse, accouchement ou puerpéralité', null, 'chapitre');
        $this->code('CIM-11', 'JB63', 'Infection des voies génito-urinaires au cours de la grossesse', $chap18->id);
    }

    private function seedMappings(): void
    {
        $pairs = [
            ['B54', 'CIM-10', '1D40', 'CIM-11'],
            ['A15', 'CIM-10', '1B10', 'CIM-11'],
            ['E10', 'CIM-10', '5A10', 'CIM-11'],
            ['E11', 'CIM-10', '5A11', 'CIM-11'],
            ['E66', 'CIM-10', '5B81', 'CIM-11'],
            ['I10', 'CIM-10', 'BA00', 'CIM-11'],
            ['I21', 'CIM-10', 'BA41', 'CIM-11'],
            ['J00', 'CIM-10', 'CA03', 'CIM-11'],
            ['J11', 'CIM-10', 'CA40', 'CIM-11'],
            ['J45', 'CIM-10', 'CA23', 'CIM-11'],
            ['K25', 'CIM-10', 'DA60', 'CIM-11'],
            ['K35', 'CIM-10', 'DB30', 'CIM-11'],
            ['M17', 'CIM-10', 'FA05', 'CIM-11'],
            ['M54.5', 'CIM-10', 'ME84.2', 'CIM-11'],
            ['S52.5', 'CIM-10', 'NC72', 'CIM-11'],
            ['S72.0', 'CIM-10', 'NC93', 'CIM-11'],
            ['O23', 'CIM-10', 'JB63', 'CIM-11'],
        ];

        foreach ($pairs as [$codeSource, $versionSource, $codeCible, $versionCible]) {
            IcdCodeMapping::query()->firstOrCreate([
                'code_source' => $codeSource,
                'version_source' => $versionSource,
                'code_cible' => $codeCible,
                'version_cible' => $versionCible,
            ]);
        }
    }

    private function code(string $version, string $code, string $label, ?int $parentId, string $level = 'code'): IcdCode
    {
        return IcdCode::query()->firstOrCreate(
            ['code' => $code, 'version' => $version],
            ['label' => $label, 'parent_id' => $parentId, 'level' => $level, 'status' => 'actif']
        );
    }
}
