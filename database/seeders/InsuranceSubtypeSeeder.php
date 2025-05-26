<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InsuranceSubtype;

class InsuranceSubtypeSeeder extends Seeder
{
    public function run(): void
    {
        InsuranceSubtype::firstOrCreate([
            'name' => 'Krankenversicherung'
        ], [
            'slug' => 'krankenversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Lebensversicherung'
        ], [
            'slug' => 'lebensversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Unfallversicherung'
        ], [
            'slug' => 'unfallversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Berufsunfähigkeitsversicherung'
        ], [
            'slug' => 'berufsunfaehigkeitsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Pflegeversicherung'
        ], [
            'slug' => 'pflegeversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Rentenversicherung'
        ], [
            'slug' => 'rentenversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Reiseversicherung'
        ], [
            'slug' => 'reiseversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Sterbegeldversicherung'
        ], [
            'slug' => 'sterbegeldversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Krankentagegeldversicherung'
        ], [
            'slug' => 'krankentagegeldversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Zahnzusatzversicherung'
        ], [
            'slug' => 'zahnzusatzversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Auslandskrankenversicherung'
        ], [
            'slug' => 'auslandskrankenversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Dread-Disease-Versicherung (für schwere Krankheiten)'
        ], [
            'slug' => 'dread-disease-versicherung-fuer-schwere-krankheiten',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Prämien-Rückerstattungsversicherung'
        ], [
            'slug' => 'praemien-rueckerstattungsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Private Pflegezusatzversicherung'
        ], [
            'slug' => 'private-pflegezusatzversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Zusatzversicherungen (wie z. B. für Krankenhäuser)'
        ], [
            'slug' => 'zusatzversicherungen-wie-z-b-fuer-krankenhaeuser',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Krankenhauszusatzversicherung'
        ], [
            'slug' => 'krankenhauszusatzversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Kapitalbildende Lebensversicherung'
        ], [
            'slug' => 'kapitalbildende-lebensversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Risiko-Lebensversicherung'
        ], [
            'slug' => 'risiko-lebensversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Fondsgebundene Lebensversicherung'
        ], [
            'slug' => 'fondsgebundene-lebensversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Rückdeckungsversicherung'
        ], [
            'slug' => 'rueckdeckungsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Risiko-Lebensversicherung für Kredite'
        ], [
            'slug' => 'risiko-lebensversicherung-fuer-kredite',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Hausratversicherung'
        ], [
            'slug' => 'hausratversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Wohngebäudeversicherung'
        ], [
            'slug' => 'wohngebaeudeversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Kfz-Versicherung'
        ], [
            'slug' => 'kfz-versicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Haftpflichtversicherung'
        ], [
            'slug' => 'haftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Teilkasko'
        ], [
            'slug' => 'teilkasko',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Vollkasko'
        ], [
            'slug' => 'vollkasko',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Glasversicherung'
        ], [
            'slug' => 'glasversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Warentransportversicherung'
        ], [
            'slug' => 'warentransportversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Luftfrachtversicherung'
        ], [
            'slug' => 'luftfrachtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Privatrechtsschutz'
        ], [
            'slug' => 'privatrechtsschutz',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Berufsrechtsschutz'
        ], [
            'slug' => 'berufsrechtsschutz',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Verkehrsrechtsschutz'
        ], [
            'slug' => 'verkehrsrechtsschutz',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Mietrechtsschutz'
        ], [
            'slug' => 'mietrechtsschutz',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Privathaftpflichtversicherung'
        ], [
            'slug' => 'privathaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Bauherrenhaftpflichtversicherung'
        ], [
            'slug' => 'bauherrenhaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Tierhalterhaftpflichtversicherung'
        ], [
            'slug' => 'tierhalterhaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Vermieterhaftpflichtversicherung'
        ], [
            'slug' => 'vermieterhaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Jagdhaftpflichtversicherung'
        ], [
            'slug' => 'jagdhaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Umwelthaftpflichtversicherung'
        ], [
            'slug' => 'umwelthaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Produkthaftpflichtversicherung'
        ], [
            'slug' => 'produkthaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Schmuckversicherung'
        ], [
            'slug' => 'schmuckversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Warenlager-Versicherung'
        ], [
            'slug' => 'warenlager-versicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Kunstversicherung'
        ], [
            'slug' => 'kunstversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Bargeldversicherung'
        ], [
            'slug' => 'bargeldversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Sammlungsversicherung (z. B. für Briefmarken, Münzen)'
        ], [
            'slug' => 'sammlungsversicherung-z-b-fuer-briefmarken-muenzen',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Photovoltaikanlagenversicherung'
        ], [
            'slug' => 'photovoltaikanlagenversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Ernteversicherung (für landwirtschaftliche Betriebe)'
        ], [
            'slug' => 'ernteversicherung-fuer-landwirtschaftliche-betriebe',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Privathaftpflichtversicherung'
        ], [
            'slug' => 'privathaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Berufshaftpflichtversicherung'
        ], [
            'slug' => 'berufshaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Bauherrenhaftpflichtversicherung'
        ], [
            'slug' => 'bauherrenhaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Tierhalterhaftpflichtversicherung'
        ], [
            'slug' => 'tierhalterhaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Umwelthaftpflichtversicherung'
        ], [
            'slug' => 'umwelthaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Produkthaftpflichtversicherung'
        ], [
            'slug' => 'produkthaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Vermieter-Haftpflichtversicherung'
        ], [
            'slug' => 'vermieter-haftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Jagd-Haftpflichtversicherung'
        ], [
            'slug' => 'jagd-haftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Feuerwehr- und Katastrophenschutz-Haftpflichtversicherung'
        ], [
            'slug' => 'feuerwehr-und-katastrophenschutz-haftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Rechtsschutzversicherung'
        ], [
            'slug' => 'rechtsschutzversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Kreditversicherung'
        ], [
            'slug' => 'kreditversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Forderungsausfallversicherung'
        ], [
            'slug' => 'forderungsausfallversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Forderungsausfallversicherung für Unternehmen'
        ], [
            'slug' => 'forderungsausfallversicherung-fuer-unternehmen',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Wertgegenstandsversicherung'
        ], [
            'slug' => 'wertgegenstandsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Baufinanzierungsversicherung'
        ], [
            'slug' => 'baufinanzierungsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Veranstaltungsversicherung'
        ], [
            'slug' => 'veranstaltungsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Eventversicherung'
        ], [
            'slug' => 'eventversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Festivalversicherung'
        ], [
            'slug' => 'festivalversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Messeversicherung'
        ], [
            'slug' => 'messeversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Warenlager-Versicherung'
        ], [
            'slug' => 'warenlager-versicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Betriebshaftpflichtversicherung'
        ], [
            'slug' => 'betriebshaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Produktversicherung'
        ], [
            'slug' => 'produktversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Geschäftsinhaltsversicherung'
        ], [
            'slug' => 'geschaeftsinhaltsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Cyber-Versicherung'
        ], [
            'slug' => 'cyber-versicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Hacker-Angriff-Versicherung'
        ], [
            'slug' => 'hacker-angriff-versicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Datenschutz-Versicherung'
        ], [
            'slug' => 'datenschutz-versicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Maschinenversicherung'
        ], [
            'slug' => 'maschinenversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Rechtsschutzversicherung für Unternehmen'
        ], [
            'slug' => 'rechtsschutzversicherung-fuer-unternehmen',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Bauleistungsversicherung'
        ], [
            'slug' => 'bauleistungsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Baugewährleistungsversicherung'
        ], [
            'slug' => 'baugewaehrleistungsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Gruppen-Lebensversicherung für Unternehmen'
        ], [
            'slug' => 'gruppen-lebensversicherung-fuer-unternehmen',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Gruppen-Unfallversicherung'
        ], [
            'slug' => 'gruppen-unfallversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Fahrzeugversicherung für Unternehmen'
        ], [
            'slug' => 'fahrzeugversicherung-fuer-unternehmen',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Ertragsausfallversicherung'
        ], [
            'slug' => 'ertragsausfallversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Flughafenversicherung'
        ], [
            'slug' => 'flughafenversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Flugzeugversicherung'
        ], [
            'slug' => 'flugzeugversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Haustier-Versicherung (Hunde, Katzen, exotische Tiere)'
        ], [
            'slug' => 'haustier-versicherung-hunde-katzen-exotische-tiere',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Hundehalterversicherung'
        ], [
            'slug' => 'hundehalterversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Pferdeversicherung'
        ], [
            'slug' => 'pferdeversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Pferdehaftpflichtversicherung'
        ], [
            'slug' => 'pferdehaftpflichtversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Spezielle Versicherung für exotische Tiere'
        ], [
            'slug' => 'spezielle-versicherung-fuer-exotische-tiere',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Bootsversicherung'
        ], [
            'slug' => 'bootsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Yachtenversicherung'
        ], [
            'slug' => 'yachtenversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Vereinsversicherung'
        ], [
            'slug' => 'vereinsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Profisportversicherung'
        ], [
            'slug' => 'profisportversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Sportgeräteversicherung'
        ], [
            'slug' => 'sportgeraeteversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Veranstaltungsversicherung'
        ], [
            'slug' => 'veranstaltungsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Jagdversicherung'
        ], [
            'slug' => 'jagdversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Zusatzversicherung für Zahnbehandlungen'
        ], [
            'slug' => 'zusatzversicherung-fuer-zahnbehandlungen',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Reiseabbruchversicherung'
        ], [
            'slug' => 'reiseabbruchversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Auslandskrankenversicherung'
        ], [
            'slug' => 'auslandskrankenversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Kosmetikversicherung (Schönheitsoperationen)'
        ], [
            'slug' => 'kosmetikversicherung-schoenheitsoperationen',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Pflegezusatzversicherung'
        ], [
            'slug' => 'pflegezusatzversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Reha-Versicherung'
        ], [
            'slug' => 'reha-versicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Reisegepäckversicherung'
        ], [
            'slug' => 'reisegepaeckversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Kosmetik- und Schönheitsoperationen-Versicherung'
        ], [
            'slug' => 'kosmetik-und-schoenheitsoperationen-versicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Kryptowährungsversicherung'
        ], [
            'slug' => 'kryptowaehrungsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Photovoltaikversicherung'
        ], [
            'slug' => 'photovoltaikversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Windparkversicherung'
        ], [
            'slug' => 'windparkversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Bausparversicherungen'
        ], [
            'slug' => 'bausparversicherungen',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Pensionsversicherung'
        ], [
            'slug' => 'pensionsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Arbeitslosenversicherung'
        ], [
            'slug' => 'arbeitslosenversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Altersvorsorgeversicherung'
        ], [
            'slug' => 'altersvorsorgeversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Unfallversicherung'
        ], [
            'slug' => 'unfallversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Nahrungsmittelversicherung'
        ], [
            'slug' => 'nahrungsmittelversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Baufinanzierungsversicherung'
        ], [
            'slug' => 'baufinanzierungsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
        InsuranceSubtype::firstOrCreate([
            'name' => 'Immobilienbewertungsversicherung'
        ], [
            'slug' => 'immobilienbewertungsversicherung',
            'description' => '',
            'weight' => 1,
            'is_active' => true,
            'average_rating_speed' => null,
            'average_rating_fairness' => null,
            'average_rating_service' => null,
            'order_column' => null
        ]);
    }
}
