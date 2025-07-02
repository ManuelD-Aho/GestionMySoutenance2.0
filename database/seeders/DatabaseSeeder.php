<?php

namespace Database\Seeders;

use App\Models\Utilisateur; // Changer App\Models\User en App\Models\Utilisateur
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Exemple de création d'utilisateur avec votre modèle Utilisateur
        Utilisateur::create([
            'numero_utilisateur' => 'SYS-2025-0002', // Exemple, assurez-vous que c'est unique et suit votre séquence
            'login_utilisateur' => 'admin.Aho',
            'email_principal' => 'ahopaul18@gmail.com',
            'mot_de_passe' => Hash::make('password'),
            'date_creation' => now(),
            'email_valide' => true,
            'statut_compte' => 'actif',
            'id_niveau_acces_donne' => 'ACCES_TOTAL', // Assurez-vous que ces IDs existent dans vos tables de référence
            'id_groupe_utilisateur' => 'GRP_ADMIN_SYS',
            'id_type_utilisateur' => 'TYPE_ADMIN',
        ]);
    }
}
