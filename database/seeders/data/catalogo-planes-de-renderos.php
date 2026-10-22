<?php

$producto = static fn (string $nombre, float $precio, bool $requiereMasa = false): array => [
    'nombre' => $nombre,
    'precio' => $precio,
    'requiere_masa' => $requiereMasa,
];

$tamanos = static function (
    string $nombre,
    float $normal,
    float $grande,
    string $etiquetaNormal = 'Normal',
    string $etiquetaGrande = 'Grande',
) use ($producto): array {
    return [
        $producto($nombre.' ('.$etiquetaNormal.')', $normal),
        $producto($nombre.' ('.$etiquetaGrande.')', $grande),
    ];
};

return [
    'grupos' => [
        [
            'nombre' => 'Entradas',
            'descripcion' => 'Entradas y botanas.',
            'area' => 'COCINA',
            'categorias' => [
                [
                    'nombre' => 'Para Empezar',
                    'productos' => [
                        $producto('TimePan con ajo (6 unidades)', 3.99),
                        $producto('Nachos', 6.00),
                        $producto('Nachos Boomwalos', 7.50),
                        $producto('Papas Fritas', 3.50),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Alitas Time',
            'descripcion' => 'Alitas, queso fundido y chicharrones.',
            'area' => 'COCINA',
            'categorias' => [
                [
                    'nombre' => 'Alitas y Chicharrones',
                    'productos' => [
                        $producto('Queso Fundido (pollo, hongos o chorizo)', 7.99),
                        $producto('Alitas (con salsa de la casa)', 7.00),
                        $producto('Chicharrones (10 onzas)', 6.50),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Love Pupusas',
            'descripcion' => 'Pupusas tradicionales, exóticas y del mar.',
            'area' => 'PUPUSAS',
            'categorias' => [
                [
                    'nombre' => 'Tradicionales',
                    'productos' => [
                        $producto('Pupusa Revuelta', 1.55, true),
                        $producto('Pupusa de Frijol con Queso', 1.55, true),
                        $producto('Pupusa de Frijol', 1.55, true),
                        $producto('Pupusa de Queso', 1.55, true),
                        $producto('Pupusa de Chicharrón con Queso', 1.55, true),
                        $producto('Pupusa de Queso con Ayote', 1.55, true),
                        $producto('Pupusa de Frijol con Chicharrón', 1.55, true),
                        $producto('Pupusa de Chicharrón', 1.55, true),
                        $producto('Pupusa de Queso con Loroco', 1.70, true),
                    ],
                ],
                [
                    'nombre' => 'Exóticas',
                    'productos' => [
                        $producto('Pupusa de Chorizo', 2.60, true),
                        $producto('Pupusa de Ajo', 2.60, true),
                        $producto('Pupusa de Jalapeño', 2.60, true),
                        $producto('Pupusa de Pollo', 2.60, true),
                        $producto('Pupusa de Hongos', 2.60, true),
                        $producto('Pupusa de Aguacate', 2.60, true),
                        $producto('Pupusa de Jamón', 2.60, true),
                    ],
                ],
                [
                    'nombre' => 'Las de Mar',
                    'productos' => [
                        $producto('Pupusa de Camarón', 3.10, true),
                        $producto('Pupusa de Pulpo', 3.10, true),
                    ],
                ],
                [
                    'nombre' => 'Especiales',
                    'productos' => [
                        $producto('Pupusa de Birria', 2.85, true),
                        $producto('Pupusa Súper Boomwalo (gigante para compartir, +14 ingredientes)', 24.99, true),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Especialidades Time',
            'descripcion' => 'Platos fuertes de la casa.',
            'area' => 'COCINA',
            'categorias' => [
                [
                    'nombre' => 'Platos Fuertes',
                    'productos' => [
                        $producto('Costilla Ahumada BBQ', 13.00),
                        $producto('Churrasco Típico', 10.50),
                        $producto('Churrasco', 9.00),
                        $producto('Pork Spare Ribs', 10.50),
                        $producto('Fajitas Boomwalos', 11.50),
                        $producto('Fajitas con Queso Fundido', 13.00),
                        $producto('Nachos Boomwalos', 8.50),
                        $producto('Arroz con Pollo', 7.00),
                        $producto('Pollo Encebollado', 7.50),
                        $producto('Torta Guanaca', 5.50),
                        $producto('Hamburguesa', 6.50),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Ensaladas y Sopas',
            'descripcion' => 'Ensaladas frescas y sopas.',
            'area' => 'COCINA',
            'categorias' => [
                [
                    'nombre' => 'Ensaladas',
                    'productos' => [
                        $producto('Ensalada César', 6.75),
                        $producto('Ensalada Primavera', 6.00),
                    ],
                ],
                [
                    'nombre' => 'Sopas',
                    'productos' => [
                        $producto('Sopa de Camarón Natural (con o sin huevo)', 8.00),
                        $producto('Sopa de Tortilla', 5.50),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Mariscos Time',
            'descripcion' => 'Camarones, ceviches y cócteles.',
            'area' => 'COCINA',
            'categorias' => [
                [
                    'nombre' => 'Mariscos',
                    'productos' => [
                        $producto('Camarones Empanizados', 13.50),
                        $producto('Camarones al Mojo de Ajo (10 unidades)', 15.00),
                        $producto('Arroz con Camarones', 8.00),
                        $producto('Ceviche de Camarón', 8.00),
                        $producto('Cóctel de Camarón en Salsa Roja', 8.00),
                        $producto('Cóctel de Camarón en Salsa Rosada', 7.75),
                        $producto('Cóctel de Camarón con Calamar', 8.50),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Mexican Time',
            'descripcion' => 'Tacos, burritos y antojitos mexicanos.',
            'area' => 'COCINA',
            'categorias' => [
                [
                    'nombre' => 'Antojitos Mexicanos',
                    'productos' => [
                        $producto('Tacos (3 tortillas - Res, Pollo o Pastor)', 5.00),
                        $producto('Tacos Mixtos (4 tortillas)', 7.00),
                        $producto('Burrito (Res o Pollo)', 7.00),
                        $producto('Burrito Mixto', 7.00),
                        $producto('Sincronizada Mixta', 7.50),
                        $producto('Baleada Mixta', 6.50),
                        $producto('Torta de Res', 6.00),
                        $producto('Quesadillas Mixtas', 5.00),
                        $producto('Tacos de Birria (3 tortillas)', 6.50),
                        $producto('Burrito de Birria', 7.50),
                        $producto('Torta de Birria', 7.00),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Hey Que Chivo',
            'descripcion' => 'Antojitos típicos salvadoreños.',
            'area' => 'COCINA',
            'categorias' => [
                [
                    'nombre' => 'Antojitos Típicos',
                    'productos' => [
                        $producto('Plátano', 1.75),
                        $producto('Plátano con Crema', 2.50),
                        $producto('Plátano con Crema y Casamiento', 3.00),
                        $producto('Cuajada Salvadoreña', 3.50),
                        $producto('Chorizo Boomwalo', 4.00),
                        $producto('Cenita Típica', 6.50),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Extras',
            'descripcion' => 'Porciones y complementos.',
            'area' => 'COCINA',
            'categorias' => [
                [
                    'nombre' => 'Complementos',
                    'productos' => [
                        $producto('Porción de casamiento', 1.00),
                        $producto('Porción de cuajada', 1.25),
                        $producto('Tortilla frita', 0.75),
                        $producto('Orden de chirmol', 0.75),
                        $producto('Porción de aguacate', 1.25),
                        $producto('Porción de frijoles volteados', 1.25),
                        $producto('Porción de crema', 1.00),
                        $producto('Tapioca', 1.50),
                    ],
                ],
            ],
        ],
        [
            'nombre' => "Pa' Compartir",
            'descripcion' => 'Platos para compartir.',
            'area' => 'COCINA',
            'categorias' => [
                [
                    'nombre' => 'Para Compartir',
                    'productos' => [
                        $producto('Boomwalo Súper Cargado', 34.99),
                        $producto('Torta Súper Boomwalo', 18.00),
                        $producto('Pupusa Súper Boomwalo (gigante para compartir, +14 ingredientes)', 24.99, true),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Refrescate / Limonadas',
            'descripcion' => 'Limonadas y refrescos naturales.',
            'area' => 'BAR',
            'categorias' => [
                [
                    'nombre' => 'Limonadas',
                    'productos' => [
                        ...$tamanos('Limonada Papá Boomwalos', 3.00, 6.00),
                        ...$tamanos('Limonada Boomwalos', 3.00, 6.00),
                        ...$tamanos('Limonada Natural', 3.00, 6.00),
                        ...$tamanos('Limonada con Fresa', 3.00, 6.00),
                        ...$tamanos('Limonada con Sandía', 3.00, 6.00),
                        ...$tamanos('Limonada con Hierba Buena', 3.00, 6.00),
                        ...$tamanos('Piña Colada', 4.00, 8.00),
                        ...$tamanos('Piña Colada con Ron', 6.00, 10.00),
                        ...$tamanos('Piña Colada con Vodka', 6.00, 10.00),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Los de Estación',
            'descripcion' => 'Frutas de temporada.',
            'area' => 'BAR',
            'categorias' => [
                [
                    'nombre' => 'Frutas de Estación',
                    'productos' => [
                        ...$tamanos('Melón', 3.00, 6.00),
                        ...$tamanos('Sandía', 3.00, 6.00),
                        ...$tamanos('Arrayán', 3.00, 6.00),
                        ...$tamanos('Mango', 3.00, 6.00),
                        ...$tamanos('Jocote', 3.00, 6.00),
                        ...$tamanos('Maracuyá', 3.00, 6.00),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Licuados con Leche',
            'descripcion' => 'Licuados y malteadas con leche.',
            'area' => 'BAR',
            'categorias' => [
                [
                    'nombre' => 'Licuados Cremosos',
                    'productos' => [
                        ...$tamanos('Horchata', 3.00, 6.00),
                        ...$tamanos('Cebada', 3.00, 6.00),
                        ...$tamanos('Fresita', 3.00, 6.00),
                        ...$tamanos('Banano', 3.00, 6.00),
                        ...$tamanos('Besito', 3.00, 6.00),
                        ...$tamanos('Coconut', 3.00, 6.00),
                        ...$tamanos('Papayazo', 3.00, 6.00),
                        ...$tamanos('Zapatazo', 3.50, 7.00),
                        ...$tamanos('Capuchino Helado', 3.50, 7.00),
                        ...$tamanos('Malteada de Chocolate', 3.50, 7.00),
                        ...$tamanos('Malteada de Galleta Oreo', 3.50, 7.00),
                        ...$tamanos('Malteada de Galleta Taro', 3.50, 7.00),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Licuados Naturales',
            'descripcion' => 'Jugos y licuados naturales.',
            'area' => 'BAR',
            'categorias' => [
                [
                    'nombre' => 'Jugos Naturales',
                    'productos' => [
                        ...$tamanos('Jugo de Naranja', 3.00, 6.00),
                        ...$tamanos('Naranja y Fresa', 3.00, 6.00),
                        ...$tamanos('Suprema', 3.00, 6.00),
                        ...$tamanos('Piña Fresa', 3.00, 6.00),
                        ...$tamanos('Naranja Piña', 3.00, 6.00),
                        ...$tamanos('Piña Papaya', 3.00, 6.00),
                        ...$tamanos('Jamaiquina', 3.00, 6.00),
                        ...$tamanos('Tamarindo', 3.00, 6.00),
                        ...$tamanos('Zanahoria', 3.00, 6.00),
                        ...$tamanos('Naranja con Zanahoria', 3.50, 7.00),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Bebidas con Alcohol',
            'descripcion' => 'Cervezas y micheladas.',
            'area' => 'BAR',
            'categorias' => [
                [
                    'nombre' => 'Cervezas',
                    'productos' => [
                        ...$tamanos('Pilsener', 2.00, 11.00, 'Individual', 'Balde'),
                        ...$tamanos('Golden', 2.00, 11.00, 'Individual', 'Balde'),
                        ...$tamanos('Suprema', 2.50, 14.00, 'Individual', 'Balde'),
                        ...$tamanos('Michelob Ultra', 3.00, 17.00, 'Individual', 'Balde'),
                        ...$tamanos('Corona', 3.00, 17.00, 'Individual', 'Balde'),
                    ],
                ],
                [
                    'nombre' => 'Micheladas',
                    'productos' => [
                        $producto('Michelada Nacional', 3.50),
                        $producto('Michelada Extranjera', 4.50),
                        $producto('Mix de Michelada', 2.50),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Té Helados',
            'descripcion' => 'Tés fríos.',
            'area' => 'BAR',
            'categorias' => [
                [
                    'nombre' => 'Tés Fríos',
                    'productos' => [
                        ...$tamanos('Té de Jamaica', 3.00, 6.00, 'Sencillo', 'Doble'),
                        ...$tamanos('Té de Limón', 3.00, 6.00, 'Sencillo', 'Doble'),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Bebidas Calientes',
            'descripcion' => 'Cafés, chocolates e infusiones.',
            'area' => 'BAR',
            'categorias' => [
                [
                    'nombre' => 'Cafés e Infusiones',
                    'productos' => [
                        $producto('Capuchino', 3.00),
                        $producto('Café', 1.75),
                        $producto('Café con Leche', 2.00),
                        $producto('Chocolate', 1.75),
                        $producto('Chocolate con Leche', 1.50),
                        $producto('Té de Manzanilla', 1.50),
                        $producto('Leche Caliente', 1.50),
                    ],
                ],
            ],
        ],
        [
            'nombre' => 'Otros',
            'descripcion' => 'Otras bebidas.',
            'area' => 'BAR',
            'categorias' => [
                [
                    'nombre' => 'Varios',
                    'productos' => [
                        $producto('Gaseosa', 1.25),
                        $producto('Botella con Agua', 1.00),
                        $producto('Agua Mineral', 1.25),
                        $producto('Agua Mineral con Limón', 2.00),
                    ],
                ],
            ],
        ],
    ],
    'combos' => [],
];
