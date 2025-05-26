<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InsuranceType;
use App\Models\InsuranceSubtype;

class InsuranceTypeInsuranceSubtypeSeeder extends Seeder
{
    public function run(): void
    {
        $type = InsuranceType::where('name', 'Personenversicherungen')->first();
        if ($type) {
            $syncData = [];
            $subtype = InsuranceSubtype::where('name', 'Krankenversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 1];
            $subtype = InsuranceSubtype::where('name', 'Lebensversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 2];
            $subtype = InsuranceSubtype::where('name', 'Unfallversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 3];
            $subtype = InsuranceSubtype::where('name', 'Berufsunfähigkeitsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 4];
            $subtype = InsuranceSubtype::where('name', 'Pflegeversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 5];
            $subtype = InsuranceSubtype::where('name', 'Rentenversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 6];
            $subtype = InsuranceSubtype::where('name', 'Reiseversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 7];
            $subtype = InsuranceSubtype::where('name', 'Sterbegeldversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 8];
            $subtype = InsuranceSubtype::where('name', 'Krankentagegeldversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 9];
            $subtype = InsuranceSubtype::where('name', 'Zahnzusatzversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 10];
            $subtype = InsuranceSubtype::where('name', 'Auslandskrankenversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 11];
            $subtype = InsuranceSubtype::where('name', 'Dread-Disease-Versicherung (für schwere Krankheiten)')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 12];
            $subtype = InsuranceSubtype::where('name', 'Prämien-Rückerstattungsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 13];
            $subtype = InsuranceSubtype::where('name', 'Private Pflegezusatzversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 14];
            $subtype = InsuranceSubtype::where('name', 'Zusatzversicherungen (wie z. B. für Krankenhäuser)')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 15];
            $subtype = InsuranceSubtype::where('name', 'Krankenhauszusatzversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 16];
            $subtype = InsuranceSubtype::where('name', 'Kapitalbildende Lebensversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 17];
            $subtype = InsuranceSubtype::where('name', 'Risiko-Lebensversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 18];
            $subtype = InsuranceSubtype::where('name', 'Fondsgebundene Lebensversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 19];
            $subtype = InsuranceSubtype::where('name', 'Rückdeckungsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 20];
            $subtype = InsuranceSubtype::where('name', 'Risiko-Lebensversicherung für Kredite')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 21];
            $type->subtypes()->syncWithoutDetaching($syncData);
        }

        $type = InsuranceType::where('name', 'Sachversicherungen')->first();
        if ($type) {
            $syncData = [];
            $subtype = InsuranceSubtype::where('name', 'Hausratversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 1];
            $subtype = InsuranceSubtype::where('name', 'Wohngebäudeversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 2];
            $subtype = InsuranceSubtype::where('name', 'Kfz-Versicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 3];
            $subtype = InsuranceSubtype::where('name', 'Haftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 4];
            $subtype = InsuranceSubtype::where('name', 'Teilkasko')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 5];
            $subtype = InsuranceSubtype::where('name', 'Vollkasko')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 6];
            $subtype = InsuranceSubtype::where('name', 'Glasversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 7];
            $subtype = InsuranceSubtype::where('name', 'Warentransportversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 8];
            $subtype = InsuranceSubtype::where('name', 'Luftfrachtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 9];
            $subtype = InsuranceSubtype::where('name', 'Privatrechtsschutz')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 10];
            $subtype = InsuranceSubtype::where('name', 'Berufsrechtsschutz')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 11];
            $subtype = InsuranceSubtype::where('name', 'Verkehrsrechtsschutz')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 12];
            $subtype = InsuranceSubtype::where('name', 'Mietrechtsschutz')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 13];
            $subtype = InsuranceSubtype::where('name', 'Privathaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 14];
            $subtype = InsuranceSubtype::where('name', 'Bauherrenhaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 15];
            $subtype = InsuranceSubtype::where('name', 'Tierhalterhaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 16];
            $subtype = InsuranceSubtype::where('name', 'Vermieterhaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 17];
            $subtype = InsuranceSubtype::where('name', 'Jagdhaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 18];
            $subtype = InsuranceSubtype::where('name', 'Umwelthaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 19];
            $subtype = InsuranceSubtype::where('name', 'Produkthaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 20];
            $subtype = InsuranceSubtype::where('name', 'Schmuckversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 21];
            $subtype = InsuranceSubtype::where('name', 'Warenlager-Versicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 22];
            $subtype = InsuranceSubtype::where('name', 'Kunstversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 23];
            $subtype = InsuranceSubtype::where('name', 'Bargeldversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 24];
            $subtype = InsuranceSubtype::where('name', 'Sammlungsversicherung (z. B. für Briefmarken, Münzen)')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 25];
            $subtype = InsuranceSubtype::where('name', 'Photovoltaikanlagenversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 26];
            $subtype = InsuranceSubtype::where('name', 'Ernteversicherung (für landwirtschaftliche Betriebe)')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 27];
            $type->subtypes()->syncWithoutDetaching($syncData);
        }

        $type = InsuranceType::where('name', 'Haftpflichtversicherungen')->first();
        if ($type) {
            $syncData = [];
            $subtype = InsuranceSubtype::where('name', 'Privathaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 1];
            $subtype = InsuranceSubtype::where('name', 'Berufshaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 2];
            $subtype = InsuranceSubtype::where('name', 'Bauherrenhaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 3];
            $subtype = InsuranceSubtype::where('name', 'Tierhalterhaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 4];
            $subtype = InsuranceSubtype::where('name', 'Umwelthaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 5];
            $subtype = InsuranceSubtype::where('name', 'Produkthaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 6];
            $subtype = InsuranceSubtype::where('name', 'Vermieter-Haftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 7];
            $subtype = InsuranceSubtype::where('name', 'Jagd-Haftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 8];
            $subtype = InsuranceSubtype::where('name', 'Feuerwehr- und Katastrophenschutz-Haftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 9];
            $type->subtypes()->syncWithoutDetaching($syncData);
        }

        $type = InsuranceType::where('name', 'Vermögensversicherungen')->first();
        if ($type) {
            $syncData = [];
            $subtype = InsuranceSubtype::where('name', 'Rechtsschutzversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 1];
            $subtype = InsuranceSubtype::where('name', 'Kreditversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 2];
            $subtype = InsuranceSubtype::where('name', 'Forderungsausfallversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 3];
            $subtype = InsuranceSubtype::where('name', 'Forderungsausfallversicherung für Unternehmen')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 4];
            $subtype = InsuranceSubtype::where('name', 'Wertgegenstandsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 5];
            $subtype = InsuranceSubtype::where('name', 'Baufinanzierungsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 6];
            $subtype = InsuranceSubtype::where('name', 'Veranstaltungsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 7];
            $subtype = InsuranceSubtype::where('name', 'Eventversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 8];
            $subtype = InsuranceSubtype::where('name', 'Festivalversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 9];
            $subtype = InsuranceSubtype::where('name', 'Messeversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 10];
            $subtype = InsuranceSubtype::where('name', 'Warenlager-Versicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 11];
            $type->subtypes()->syncWithoutDetaching($syncData);
        }

        $type = InsuranceType::where('name', 'Gewerbe- und Unternehmensversicherungen')->first();
        if ($type) {
            $syncData = [];
            $subtype = InsuranceSubtype::where('name', 'Betriebshaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 1];
            $subtype = InsuranceSubtype::where('name', 'Produktversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 2];
            $subtype = InsuranceSubtype::where('name', 'Geschäftsinhaltsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 3];
            $subtype = InsuranceSubtype::where('name', 'Cyber-Versicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 4];
            $subtype = InsuranceSubtype::where('name', 'Hacker-Angriff-Versicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 5];
            $subtype = InsuranceSubtype::where('name', 'Datenschutz-Versicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 6];
            $subtype = InsuranceSubtype::where('name', 'Maschinenversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 7];
            $subtype = InsuranceSubtype::where('name', 'Rechtsschutzversicherung für Unternehmen')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 8];
            $subtype = InsuranceSubtype::where('name', 'Bauleistungsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 9];
            $subtype = InsuranceSubtype::where('name', 'Baugewährleistungsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 10];
            $subtype = InsuranceSubtype::where('name', 'Gruppen-Lebensversicherung für Unternehmen')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 11];
            $subtype = InsuranceSubtype::where('name', 'Gruppen-Unfallversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 12];
            $subtype = InsuranceSubtype::where('name', 'Fahrzeugversicherung für Unternehmen')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 13];
            $subtype = InsuranceSubtype::where('name', 'Ertragsausfallversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 14];
            $type->subtypes()->syncWithoutDetaching($syncData);
        }

        $type = InsuranceType::where('name', 'Spezialversicherungen')->first();
        if ($type) {
            $syncData = [];
            $subtype = InsuranceSubtype::where('name', 'Flughafenversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 1];
            $subtype = InsuranceSubtype::where('name', 'Flugzeugversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 2];
            $subtype = InsuranceSubtype::where('name', 'Haustier-Versicherung (Hunde, Katzen, exotische Tiere)')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 3];
            $subtype = InsuranceSubtype::where('name', 'Hundehalterversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 4];
            $subtype = InsuranceSubtype::where('name', 'Pferdeversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 5];
            $subtype = InsuranceSubtype::where('name', 'Pferdehaftpflichtversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 6];
            $subtype = InsuranceSubtype::where('name', 'Spezielle Versicherung für exotische Tiere')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 7];
            $subtype = InsuranceSubtype::where('name', 'Bootsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 8];
            $subtype = InsuranceSubtype::where('name', 'Yachtenversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 9];
            $subtype = InsuranceSubtype::where('name', 'Vereinsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 10];
            $subtype = InsuranceSubtype::where('name', 'Profisportversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 11];
            $subtype = InsuranceSubtype::where('name', 'Sportgeräteversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 12];
            $subtype = InsuranceSubtype::where('name', 'Veranstaltungsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 13];
            $subtype = InsuranceSubtype::where('name', 'Jagdversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 14];
            $type->subtypes()->syncWithoutDetaching($syncData);
        }

        $type = InsuranceType::where('name', 'Zusatzversicherungen')->first();
        if ($type) {
            $syncData = [];
            $subtype = InsuranceSubtype::where('name', 'Zusatzversicherung für Zahnbehandlungen')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 1];
            $subtype = InsuranceSubtype::where('name', 'Reiseabbruchversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 2];
            $subtype = InsuranceSubtype::where('name', 'Auslandskrankenversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 3];
            $subtype = InsuranceSubtype::where('name', 'Kosmetikversicherung (Schönheitsoperationen)')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 4];
            $subtype = InsuranceSubtype::where('name', 'Pflegezusatzversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 5];
            $subtype = InsuranceSubtype::where('name', 'Reha-Versicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 6];
            $subtype = InsuranceSubtype::where('name', 'Reisegepäckversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 7];
            $subtype = InsuranceSubtype::where('name', 'Kosmetik- und Schönheitsoperationen-Versicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 8];
            $type->subtypes()->syncWithoutDetaching($syncData);
        }

        $type = InsuranceType::where('name', 'Weitere Versicherungen')->first();
        if ($type) {
            $syncData = [];
            $subtype = InsuranceSubtype::where('name', 'Kryptowährungsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 1];
            $subtype = InsuranceSubtype::where('name', 'Photovoltaikversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 2];
            $subtype = InsuranceSubtype::where('name', 'Windparkversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 3];
            $subtype = InsuranceSubtype::where('name', 'Bausparversicherungen')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 4];
            $subtype = InsuranceSubtype::where('name', 'Pensionsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 5];
            $subtype = InsuranceSubtype::where('name', 'Arbeitslosenversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 6];
            $subtype = InsuranceSubtype::where('name', 'Altersvorsorgeversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 7];
            $subtype = InsuranceSubtype::where('name', 'Unfallversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 8];
            $subtype = InsuranceSubtype::where('name', 'Nahrungsmittelversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 9];
            $subtype = InsuranceSubtype::where('name', 'Baufinanzierungsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 10];
            $subtype = InsuranceSubtype::where('name', 'Immobilienbewertungsversicherung')->first();
            if ($subtype) $syncData[$subtype->id] = ['order_id' => 11];
            $type->subtypes()->syncWithoutDetaching($syncData);
        }

    }
}