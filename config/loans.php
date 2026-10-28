<?php

// Valeurs par défaut : un administrateur peut les modifier depuis la page Paramètres
// (enregistrées en base, elles priment alors sur ces valeurs).
return [

    // Durée d'un prêt, en jours (aussi utilisée pour une prolongation)
    'duration_days' => (int) env('LOAN_DURATION_DAYS', 21),

    // Nombre de livres qu'un même lecteur peut emprunter en même temps (0 = illimité)
    'max_active_per_user' => (int) env('LOAN_MAX_ACTIVE_PER_USER', 1),

    // Nombre de prolongations autorisées par emprunt
    'max_renewals' => (int) env('LOAN_MAX_RENEWALS', 1),

    // Un emprunt est « bientôt à rendre » à partir de ce nombre de jours restants
    'due_soon_days' => (int) env('LOAN_DUE_SOON_DAYS', 3),

    // Nom de la bibliothèque affiché dans l'interface
    'library_name' => env('LIBRARY_NAME', 'Gestion de Livres'),

];
