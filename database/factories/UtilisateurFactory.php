<?php

namespace Database\Factories;

use App\Models\Utilisateur; // Changer App\Models\User en App\Models\Utilisateur
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UtilisateurFactory extends Factory // Changer UserFactory en UtilisateurFactory
{
    protected $model = Utilisateur::class; // Spécifier le modèle

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'numero_utilisateur' => 'ETU-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT), // Exemple de génération
            'login_utilisateur' => fake()->unique()->userName(),
            'email_principal' => fake()->unique()->safeEmail(),
            'mot_de_passe' => static::$password ??= Hash::make('password'),
            'date_creation' => now(),
            'email_valide' => fake()->boolean(),
            'statut_compte' => fake()->randomElement(['actif', 'inactif', 'en_attente_validation']),
            'id_niveau_acces_donne' => fake()->randomElement(['ACCES_PERSONNEL', 'ACCES_DEPARTEMENT', 'ACCES_TOTAL']), // Assurez-vous que ces IDs existent
            'id_groupe_utilisateur' => fake()->randomElement(['GRP_ETUDIANT', 'GRP_ENSEIGNANT', 'GRP_PERS_ADMIN']),
            'id_type_utilisateur' => fake()->randomElement(['TYPE_ETUD', 'TYPE_ENS', 'TYPE_PERS_ADMIN']),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_valide' => false,
        ]);
    }
}
