<?php

return [

    // Durée d'un prêt, en jours (aussi utilisée pour une prolongation)
    'duration_days' => (int) env('LOAN_DURATION_DAYS', 21),

    // Nombre de livres qu'un même lecteur peut emprunter en même temps
    'max_active_per_user' => (int) env('LOAN_MAX_ACTIVE_PER_USER', 1),

    // Nombre de prolongations autorisées par emprunt
    'max_renewals' => (int) env('LOAN_MAX_RENEWALS', 1),

];
