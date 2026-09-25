<?php

return [
    'grupos' => [
        [
            'nombre' => 'Pupusas',
            'descripcion' => 'Pupusas de la casa.',
            'icono' => '🫓',
            'categorias' => [
                [
                    'nombre' => 'Pupusas Normales',
                    'descripcion' => 'Pupusas clásicas del menú.',
                    'productos' => [
                        ['nombre' => 'Pupusa Revuelta', 'precio' => 1.25, 'requiere_masa' => true, 'imagen' => 'Boomwalos_Revuelta.jpg'],
                        ['nombre' => 'Pupusa de Frijol con Queso', 'precio' => 1.25, 'requiere_masa' => true, 'imagen' => 'Boomwalos_FrijolConQueso.jpg'],
                        ['nombre' => 'Pupusa de Chicharron con Queso', 'precio' => 1.25, 'requiere_masa' => true, 'imagen' => 'Boomwalos_ChicharronConQueso.jpg'],
                        ['nombre' => 'Pupusa de Queso', 'precio' => 1.40, 'requiere_masa' => true, 'imagen' => 'Boomwalos_Queso.png'],
                        ['nombre' => 'Pupusa de Queso con Loroco', 'precio' => 1.50, 'requiere_masa' => true, 'imagen' => 'Boomwalos_QuesoConLoroco.jpg'],
                    ],
                ],
                [
                    'nombre' => 'Pupusas Especiales',
                    'descripcion' => 'Sabores especiales.',
                    'productos' => [
                        ['nombre' => 'Pupusa de Pollo con Queso', 'precio' => 2.50, 'requiere_masa' => true, 'imagen' => 'Boomwalos_PolloConQueso.jpg'],
                        ['nombre' => 'Pupusa de Camaron con Queso', 'precio' => 3.00, 'requiere_masa' => true, 'imagen' => 'Boomwalos_CamaronConQueso.jpg'],
                        ['nombre' => 'Pupusa de Ajo', 'precio' => 2.50, 'requiere_masa' => true, 'imagen' => 'Boomwalos_Ajo.jpg'],
                        ['nombre' => 'Pupusa de Chorizo', 'precio' => 2.50, 'requiere_masa' => true, 'imagen' => 'Boomwalos_Chorizo.jpg'],
                        ['nombre' => 'Pupusa de Birria', 'precio' => 3.00, 'requiere_masa' => true, 'imagen' => 'Boomwalos_Birria.png'],
                        ['nombre' => 'Pupusa de Hongo', 'precio' => 2.50, 'requiere_masa' => true, 'imagen' => 'Boomwalos_hongos.jpg'],
                        ['nombre' => 'Pupusa de Jalapeno', 'precio' => 2.50, 'requiere_masa' => true, 'imagen' => 'Boomwalos_Jalapeno.jpg'],
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Bebidas',
            'descripcion' => 'Bebidas frías y calientes.',
            'icono' => '🥤',
            'categorias' => [
                [
                    'nombre' => 'Bebidas Calientes',
                    'descripcion' => 'Café e infusiones.',
                    'productos' => [
                        ['nombre' => 'Chocolate', 'precio' => 1.25, 'requiere_masa' => false, 'imagen' => 'Boomwalos_Chocolate.jpg'],
                        ['nombre' => 'Cafe', 'precio' => 1.00, 'requiere_masa' => false, 'imagen' => 'Boomwalos_Cafe.jpg'],
                    ],
                ],
                [
                    'nombre' => 'Bebidas Frias',
                    'descripcion' => 'Bebidas frías y refrescos.',
                    'productos' => [
                        ['nombre' => 'Horchata', 'precio' => 2.00, 'requiere_masa' => false, 'imagen' => 'Boomwalos_horchata.jpg'],
                        ['nombre' => 'Agua', 'precio' => 1.00, 'requiere_masa' => false, 'imagen' => 'Boomwalos_Agua.webp'],
                    ],
                ],
                [
                    'nombre' => 'Sodas',
                    'descripcion' => 'Sodas y gaseosas.',
                    'productos' => [
                        ['nombre' => 'Soda', 'precio' => 1.00, 'requiere_masa' => false, 'imagen' => 'coca.webp'],
                        ['nombre' => 'Kolashampagne', 'precio' => 0.75, 'requiere_masa' => false, 'imagen' => 'kolashanpan.webp'],
                        ['nombre' => 'Soda 2 Litros', 'precio' => 2.50, 'requiere_masa' => false, 'imagen' => 'Coca-2Litros.jpg'],
                    ],
                ],
            ],
        ],
    ],
    'combos' => [
        [
            'nombre' => 'Combo #1',
            'precio_fijo' => 7.50,
            'imagen' => 'Combos/Combo1.jpg',
            'opciones' => [
                [
                    'nombre' => 'Pupusas',
                    'cantidad_requerida' => 5,
                    'es_obligatorio' => true,
                    'productos' => ['Pupusa Revuelta', 'Pupusa de Frijol con Queso', 'Pupusa de Chicharron con Queso', 'Pupusa de Queso', 'Pupusa de Queso con Loroco'],
                ],
                [
                    'nombre' => 'Sodas',
                    'cantidad_requerida' => 2,
                    'es_obligatorio' => true,
                    'productos' => ['Kolashampagne', 'Soda'],
                ],
            ],
        ],
        [
            'nombre' => 'Combo #2',
            'precio_fijo' => 11.75,
            'imagen' => 'Combos/Combo2.jpg',
            'opciones' => [
                [
                    'nombre' => 'Pupusas',
                    'cantidad_requerida' => 8,
                    'es_obligatorio' => true,
                    'productos' => ['Pupusa Revuelta', 'Pupusa de Frijol con Queso', 'Pupusa de Chicharron con Queso', 'Pupusa de Queso', 'Pupusa de Queso con Loroco'],
                ],
                [
                    'nombre' => 'Sodas',
                    'cantidad_requerida' => 3,
                    'es_obligatorio' => true,
                    'productos' => ['Kolashampagne', 'Soda'],
                ],
            ],
        ],
        [
            'nombre' => 'Combo #3',
            'precio_fijo' => 16.80,
            'imagen' => 'Combos/Combo3.jpg',
            'opciones' => [
                [
                    'nombre' => 'Pupusas',
                    'cantidad_requerida' => 12,
                    'es_obligatorio' => true,
                    'productos' => ['Pupusa Revuelta', 'Pupusa de Frijol con Queso', 'Pupusa de Chicharron con Queso', 'Pupusa de Queso', 'Pupusa de Queso con Loroco'],
                ],
                [
                    'nombre' => 'Sodas',
                    'cantidad_requerida' => 4,
                    'es_obligatorio' => true,
                    'productos' => ['Kolashampagne', 'Soda', 'Soda 2 Litros'],
                ],
            ],
        ],
        [
            'nombre' => 'Combo #4',
            'precio_fijo' => 22.50,
            'imagen' => 'Combos/Combo4.jpg',
            'opciones' => [
                [
                    'nombre' => 'Pupusas',
                    'cantidad_requerida' => 16,
                    'es_obligatorio' => true,
                    'productos' => ['Pupusa Revuelta', 'Pupusa de Frijol con Queso', 'Pupusa de Chicharron con Queso', 'Pupusa de Queso', 'Pupusa de Queso con Loroco'],
                ],
                [
                    'nombre' => 'Sodas',
                    'cantidad_requerida' => 5,
                    'es_obligatorio' => true,
                    'productos' => ['Kolashampagne', 'Soda', 'Soda 2 Litros'],
                ],
            ],
        ],
    ],
];
