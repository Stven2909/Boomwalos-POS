<?php

// Catálogo real de Los Boomwalos Planes de Renderos (establecimiento_id=3)
// Sincronizado con la base de datos productiva

$producto = static fn (string $nombre, float $precio, bool $requiereMasa = false): array => [
    'nombre' => $nombre,
    'precio' => $precio,
    'requiere_masa' => $requiereMasa,
];

return array (
  'grupos' => 
  array (
    0 => 
    array (
      'nombre' => 'Entradas',
      'descripcion' => 'Entradas y botanas.',
      'area' => 'COCINA',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Nachos',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Nachos Boomwalos',
          'precio' => 7.5,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Pan con ajo (6 unidades)',
          'precio' => 3.99,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Papas Fritas',
          'precio' => 3.5,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Para Empezar',
        ),
      ),
    ),
    1 => 
    array (
      'nombre' => 'Alitas Time',
      'descripcion' => 'Alitas, queso fundido y chicharrones.',
      'area' => 'COCINA',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Alitas (con salsa de la casa)',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Chicharrones (10 onzas)',
          'precio' => 6.5,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Queso Fundido (pollo, hongos o chorizo)',
          'precio' => 7.99,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Alitas y Chicharrones',
        ),
      ),
    ),
    2 => 
    array (
      'nombre' => 'Love Pupusas',
      'descripcion' => 'Pupusas tradicionales, exóticas y del mar.',
      'area' => 'PUPUSAS',
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Tradicionales',
          'productos' => 
          array (
            0 => 
            array (
              'nombre' => 'Pupusa de Chicharrón',
              'precio' => 1.55,
              'requiere_masa' => true,
            ),
            1 => 
            array (
              'nombre' => 'Pupusa de Chicharrón con Queso',
              'precio' => 1.55,
              'requiere_masa' => true,
            ),
            2 => 
            array (
              'nombre' => 'Pupusa de Frijol',
              'precio' => 1.55,
              'requiere_masa' => true,
            ),
            3 => 
            array (
              'nombre' => 'Pupusa de Frijol con Chicharrón',
              'precio' => 1.55,
              'requiere_masa' => true,
            ),
            4 => 
            array (
              'nombre' => 'Pupusa de Frijol con Queso',
              'precio' => 1.55,
              'requiere_masa' => true,
            ),
            5 => 
            array (
              'nombre' => 'Pupusa de Queso',
              'precio' => 1.55,
              'requiere_masa' => true,
            ),
            6 => 
            array (
              'nombre' => 'Pupusa de Queso con Ayote',
              'precio' => 1.55,
              'requiere_masa' => true,
            ),
            7 => 
            array (
              'nombre' => 'Pupusa de Queso con Loroco',
              'precio' => 1.7,
              'requiere_masa' => true,
            ),
            8 => 
            array (
              'nombre' => 'Pupusa Revuelta',
              'precio' => 1.55,
              'requiere_masa' => true,
            ),
          ),
        ),
        1 => 
        array (
          'nombre' => 'Exóticas',
          'productos' => 
          array (
            0 => 
            array (
              'nombre' => 'Pupusa de Aguacate',
              'precio' => 2.6,
              'requiere_masa' => true,
            ),
            1 => 
            array (
              'nombre' => 'Pupusa de Ajo',
              'precio' => 2.6,
              'requiere_masa' => true,
            ),
            2 => 
            array (
              'nombre' => 'Pupusa de Chorizo',
              'precio' => 2.6,
              'requiere_masa' => true,
            ),
            3 => 
            array (
              'nombre' => 'Pupusa de Hongos',
              'precio' => 2.6,
              'requiere_masa' => true,
            ),
            4 => 
            array (
              'nombre' => 'Pupusa de Jalapeño',
              'precio' => 2.6,
              'requiere_masa' => true,
            ),
            5 => 
            array (
              'nombre' => 'Pupusa de Jamón',
              'precio' => 2.6,
              'requiere_masa' => true,
            ),
            6 => 
            array (
              'nombre' => 'Pupusa de Pollo',
              'precio' => 2.6,
              'requiere_masa' => true,
            ),
          ),
        ),
        2 => 
        array (
          'nombre' => 'Las de Mar',
          'productos' => 
          array (
            0 => 
            array (
              'nombre' => 'Pupusa de Camarón',
              'precio' => 3.1,
              'requiere_masa' => true,
            ),
            1 => 
            array (
              'nombre' => 'Pupusa de Pulpo',
              'precio' => 3.1,
              'requiere_masa' => true,
            ),
          ),
        ),
        3 => 
        array (
          'nombre' => 'Especiales',
          'productos' => 
          array (
            0 => 
            array (
              'nombre' => 'Pupusa de Birria',
              'precio' => 2.85,
              'requiere_masa' => true,
            ),
            1 => 
            array (
              'nombre' => 'Pupusa Súper Boomwalo (gigante para compartir, +14 ingredientes)',
              'precio' => 24.99,
              'requiere_masa' => true,
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'nombre' => 'Especialidades Time',
      'descripcion' => 'Platos fuertes de la casa.',
      'area' => 'COCINA',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Arroz con Pollo',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Churrasco',
          'precio' => 9.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Churrasco Típico',
          'precio' => 10.5,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Costilla Ahumada BBQ',
          'precio' => 13.0,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Fajitas Boomwalos',
          'precio' => 11.5,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Fajitas con Queso Fundido',
          'precio' => 13.0,
          'requiere_masa' => false,
        ),
        6 => 
        array (
          'nombre' => 'Hamburguesa',
          'precio' => 6.5,
          'requiere_masa' => false,
        ),
        7 => 
        array (
          'nombre' => 'Nachos Boomwalos',
          'precio' => 8.5,
          'requiere_masa' => false,
        ),
        8 => 
        array (
          'nombre' => 'Pollo Encebollado',
          'precio' => 7.5,
          'requiere_masa' => false,
        ),
        9 => 
        array (
          'nombre' => 'Pork Spare Ribs',
          'precio' => 10.5,
          'requiere_masa' => false,
        ),
        10 => 
        array (
          'nombre' => 'Torta Guanaca',
          'precio' => 5.5,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Platos Fuertes',
        ),
      ),
    ),
    4 => 
    array (
      'nombre' => 'Ensaladas y Sopas',
      'descripcion' => 'Ensaladas frescas y sopas.',
      'area' => 'COCINA',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Ensalada César',
          'precio' => 6.75,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Ensalada Primavera',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Sopa de Camarón Natural (con o sin huevo)',
          'precio' => 8.0,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Sopa de Tortilla',
          'precio' => 5.5,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Ensaladas',
        ),
        1 => 
        array (
          'nombre' => 'Sopas',
        ),
      ),
    ),
    5 => 
    array (
      'nombre' => 'Mariscos Time',
      'descripcion' => 'Camarones, ceviches y cócteles.',
      'area' => 'COCINA',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Arroz con Camarones',
          'precio' => 8.0,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Camarones al Mojo de Ajo',
          'precio' => 15.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Camarones Empanizados',
          'precio' => 13.5,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Ceviche de Camarón',
          'precio' => 8.0,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Cóctel de Camarón con Calamar',
          'precio' => 8.5,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Cóctel de Camarón en Salsa Roja',
          'precio' => 8.0,
          'requiere_masa' => false,
        ),
        6 => 
        array (
          'nombre' => 'Cóctel de Camarón en Salsa Rosada',
          'precio' => 7.75,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Mariscos',
        ),
      ),
    ),
    6 => 
    array (
      'nombre' => 'Mexican Time',
      'descripcion' => 'Tacos, burritos y antojitos mexicanos.',
      'area' => 'COCINA',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Baleada Mixta',
          'precio' => 6.5,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Burrito (Res o Pollo)',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Burrito de Birria',
          'precio' => 7.5,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Burrito Mixto',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Quesadillas Mixtas',
          'precio' => 5.0,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Sincronizada Mixta',
          'precio' => 7.5,
          'requiere_masa' => false,
        ),
        6 => 
        array (
          'nombre' => 'Tacos (3 tortillas - Res, Pollo o Pastor)',
          'precio' => 5.0,
          'requiere_masa' => false,
        ),
        7 => 
        array (
          'nombre' => 'Tacos de Birria (3 tortillas)',
          'precio' => 6.5,
          'requiere_masa' => false,
        ),
        8 => 
        array (
          'nombre' => 'Tacos Mixtos (4 tortillas)',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        9 => 
        array (
          'nombre' => 'Torta de Birria',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        10 => 
        array (
          'nombre' => 'Torta de Res',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Antojitos Mexicanos',
        ),
      ),
    ),
    7 => 
    array (
      'nombre' => 'Hey Que Chivo',
      'descripcion' => 'Antojitos típicos salvadoreños.',
      'area' => 'COCINA',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Cenita Típica',
          'precio' => 6.5,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Chorizo Boomwalo',
          'precio' => 4.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Cuajada Salvadoreña',
          'precio' => 3.5,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Plátano',
          'precio' => 1.75,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Plátano con Crema',
          'precio' => 2.5,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Plátano con Crema y Casamiento',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Antojitos Típicos',
        ),
      ),
    ),
    8 => 
    array (
      'nombre' => 'Extras',
      'descripcion' => 'Porciones y complementos.',
      'area' => 'COCINA',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Orden de chirmol',
          'precio' => 0.75,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Porción de aguacate',
          'precio' => 1.25,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Porción de casamiento',
          'precio' => 1.0,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Porción de crema',
          'precio' => 1.0,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Porción de cuajada',
          'precio' => 1.25,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Porción de frijoles volteados',
          'precio' => 1.25,
          'requiere_masa' => false,
        ),
        6 => 
        array (
          'nombre' => 'Tortilla frita',
          'precio' => 0.75,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Complementos',
          'productos' => 
          array (
            0 => 
            array (
              'nombre' => 'Tapioca',
              'precio' => 1.5,
              'requiere_masa' => false,
            ),
          ),
        ),
      ),
    ),
    9 => 
    array (
      'nombre' => 'Pa\' Compartir',
      'descripcion' => 'Platos para compartir.',
      'area' => 'COCINA',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Boomwalo Súper Cargado',
          'precio' => 34.99,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Pupusa Súper Boomwalo (gigante para compartir, +14 ingredientes)',
          'precio' => 24.99,
          'requiere_masa' => true,
        ),
        2 => 
        array (
          'nombre' => 'Torta Súper Boomwalo',
          'precio' => 18.0,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Para Compartir',
        ),
      ),
    ),
    10 => 
    array (
      'nombre' => 'Refrescate / Limonadas',
      'descripcion' => 'Limonadas y refrescos naturales.',
      'area' => 'BAR',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Limonada Boomwalos (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Limonada Boomwalos (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Limonada con Fresa (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Limonada con Fresa (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Limonada con Hierba Buena (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Limonada con Hierba Buena (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        6 => 
        array (
          'nombre' => 'Limonada con Sandía (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        7 => 
        array (
          'nombre' => 'Limonada con Sandía (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        8 => 
        array (
          'nombre' => 'Limonada Natural (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        9 => 
        array (
          'nombre' => 'Limonada Natural (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        10 => 
        array (
          'nombre' => 'Limonada Papá Boomwalos (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        11 => 
        array (
          'nombre' => 'Limonada Papá Boomwalos (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        12 => 
        array (
          'nombre' => 'Piña Colada (Grande)',
          'precio' => 8.0,
          'requiere_masa' => false,
        ),
        13 => 
        array (
          'nombre' => 'Piña Colada (Normal)',
          'precio' => 4.0,
          'requiere_masa' => false,
        ),
        14 => 
        array (
          'nombre' => 'Piña Colada con Ron (Grande)',
          'precio' => 10.0,
          'requiere_masa' => false,
        ),
        15 => 
        array (
          'nombre' => 'Piña Colada con Ron (Normal)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        16 => 
        array (
          'nombre' => 'Piña Colada con Vodka (Grande)',
          'precio' => 10.0,
          'requiere_masa' => false,
        ),
        17 => 
        array (
          'nombre' => 'Piña Colada con Vodka (Normal)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Limonadas',
        ),
      ),
    ),
    11 => 
    array (
      'nombre' => 'Los de Estación',
      'descripcion' => 'Frutas de temporada.',
      'area' => 'BAR',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Arrayán (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Arrayán (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Jocote (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Jocote (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Mango (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Mango (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        6 => 
        array (
          'nombre' => 'Maracuyá (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        7 => 
        array (
          'nombre' => 'Maracuyá (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        8 => 
        array (
          'nombre' => 'Melón (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        9 => 
        array (
          'nombre' => 'Melón (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        10 => 
        array (
          'nombre' => 'Sandía (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        11 => 
        array (
          'nombre' => 'Sandía (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Frutas de Estación',
        ),
      ),
    ),
    12 => 
    array (
      'nombre' => 'Licuados con Leche',
      'descripcion' => 'Licuados y malteadas con leche.',
      'area' => 'BAR',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Banano (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Banano (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Besito (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Besito (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Capuchino Helado (Grande)',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Capuchino Helado (Normal)',
          'precio' => 3.5,
          'requiere_masa' => false,
        ),
        6 => 
        array (
          'nombre' => 'Cebada (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        7 => 
        array (
          'nombre' => 'Cebada (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        8 => 
        array (
          'nombre' => 'Coconut (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        9 => 
        array (
          'nombre' => 'Coconut (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        10 => 
        array (
          'nombre' => 'Fresita (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        11 => 
        array (
          'nombre' => 'Fresita (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        12 => 
        array (
          'nombre' => 'Horchata (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        13 => 
        array (
          'nombre' => 'Horchata (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        14 => 
        array (
          'nombre' => 'Malteada de Chocolate (Grande)',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        15 => 
        array (
          'nombre' => 'Malteada de Chocolate (Normal)',
          'precio' => 3.5,
          'requiere_masa' => false,
        ),
        16 => 
        array (
          'nombre' => 'Malteada de Galleta Oreo (Grande)',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        17 => 
        array (
          'nombre' => 'Malteada de Galleta Oreo (Normal)',
          'precio' => 3.5,
          'requiere_masa' => false,
        ),
        18 => 
        array (
          'nombre' => 'Malteada de Galleta Taro (Grande)',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        19 => 
        array (
          'nombre' => 'Malteada de Galleta Taro (Normal)',
          'precio' => 3.5,
          'requiere_masa' => false,
        ),
        20 => 
        array (
          'nombre' => 'Papayazo (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        21 => 
        array (
          'nombre' => 'Papayazo (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        22 => 
        array (
          'nombre' => 'Zapotazo (Grande)',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        23 => 
        array (
          'nombre' => 'Zapotazo (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Licuados Cremosos',
          'productos' => 
          array (
            0 => 
            array (
              'nombre' => 'Zapatazo (Grande)',
              'precio' => 7.0,
              'requiere_masa' => false,
            ),
            1 => 
            array (
              'nombre' => 'Zapatazo (Normal)',
              'precio' => 3.5,
              'requiere_masa' => false,
            ),
          ),
        ),
      ),
    ),
    13 => 
    array (
      'nombre' => 'Licuados Naturales',
      'descripcion' => 'Jugos y licuados naturales.',
      'area' => 'BAR',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Jamaiquina (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Jamaiquina (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Jugo de Naranja (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Jugo de Naranja (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Naranja con Zanahoria (Grande)',
          'precio' => 7.0,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Naranja con Zanahoria (Normal)',
          'precio' => 3.5,
          'requiere_masa' => false,
        ),
        6 => 
        array (
          'nombre' => 'Naranja Piña (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        7 => 
        array (
          'nombre' => 'Naranja Piña (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        8 => 
        array (
          'nombre' => 'Naranja y Fresa (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        9 => 
        array (
          'nombre' => 'Naranja y Fresa (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        10 => 
        array (
          'nombre' => 'Piña Fresa (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        11 => 
        array (
          'nombre' => 'Piña Fresa (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        12 => 
        array (
          'nombre' => 'Piña Papaya (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        13 => 
        array (
          'nombre' => 'Piña Papaya (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        14 => 
        array (
          'nombre' => 'Tamarindo (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        15 => 
        array (
          'nombre' => 'Tamarindo (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        16 => 
        array (
          'nombre' => 'Tapioca',
          'precio' => 1.5,
          'requiere_masa' => false,
        ),
        17 => 
        array (
          'nombre' => 'Zanahoria (Grande)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        18 => 
        array (
          'nombre' => 'Zanahoria (Normal)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Jugos Naturales',
          'productos' => 
          array (
            0 => 
            array (
              'nombre' => 'Naranja con Zanahoria (Grande)',
              'precio' => 7.0,
              'requiere_masa' => false,
            ),
            1 => 
            array (
              'nombre' => 'Naranja con Zanahoria (Normal)',
              'precio' => 3.5,
              'requiere_masa' => false,
            ),
            2 => 
            array (
              'nombre' => 'Zanahoria (Grande)',
              'precio' => 6.0,
              'requiere_masa' => false,
            ),
            3 => 
            array (
              'nombre' => 'Zanahoria (Normal)',
              'precio' => 3.0,
              'requiere_masa' => false,
            ),
          ),
        ),
      ),
    ),
    14 => 
    array (
      'nombre' => 'Bebidas con Alcohol',
      'descripcion' => 'Cervezas y micheladas.',
      'area' => 'BAR',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Corona (Balde)',
          'precio' => 17.0,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Corona (Individual)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Golden (Balde)',
          'precio' => 11.0,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Golden (Individual)',
          'precio' => 2.0,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Michelada Extranjera',
          'precio' => 4.5,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Michelada Nacional',
          'precio' => 3.5,
          'requiere_masa' => false,
        ),
        6 => 
        array (
          'nombre' => 'Michelob Ultra (Balde)',
          'precio' => 17.0,
          'requiere_masa' => false,
        ),
        7 => 
        array (
          'nombre' => 'Michelob Ultra (Individual)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        8 => 
        array (
          'nombre' => 'Mix de Michelada',
          'precio' => 2.5,
          'requiere_masa' => false,
        ),
        9 => 
        array (
          'nombre' => 'Pilsener (Balde)',
          'precio' => 11.0,
          'requiere_masa' => false,
        ),
        10 => 
        array (
          'nombre' => 'Pilsener (Individual)',
          'precio' => 2.0,
          'requiere_masa' => false,
        ),
        11 => 
        array (
          'nombre' => 'Piña Colada con Ron (Grande)',
          'precio' => 10.0,
          'requiere_masa' => false,
        ),
        12 => 
        array (
          'nombre' => 'Piña Colada con Ron (Normal)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        13 => 
        array (
          'nombre' => 'Piña Colada con Vodka (Grande)',
          'precio' => 10.0,
          'requiere_masa' => false,
        ),
        14 => 
        array (
          'nombre' => 'Piña Colada con Vodka (Normal)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        15 => 
        array (
          'nombre' => 'Suprema (Balde)',
          'precio' => 14.0,
          'requiere_masa' => false,
        ),
        16 => 
        array (
          'nombre' => 'Suprema (Individual)',
          'precio' => 2.5,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Cervezas',
          'productos' => 
          array (
            0 => 
            array (
              'nombre' => 'Corona (Balde)',
              'precio' => 17.0,
              'requiere_masa' => false,
            ),
            1 => 
            array (
              'nombre' => 'Corona (Individual)',
              'precio' => 3.0,
              'requiere_masa' => false,
            ),
          ),
        ),
        1 => 
        array (
          'nombre' => 'Micheladas',
        ),
      ),
    ),
    15 => 
    array (
      'nombre' => 'Té Helados',
      'descripcion' => 'Tés fríos.',
      'area' => 'BAR',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Té de Jamaica (Doble)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Té de Jamaica (Sencillo)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Té de Limón (Doble)',
          'precio' => 6.0,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Té de Limón (Sencillo)',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Tés Fríos',
        ),
      ),
    ),
    16 => 
    array (
      'nombre' => 'Bebidas Calientes',
      'descripcion' => 'Cafés, chocolates e infusiones.',
      'area' => 'BAR',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Café',
          'precio' => 1.75,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Café con Leche',
          'precio' => 2.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Capuchino',
          'precio' => 3.0,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Chocolate',
          'precio' => 1.75,
          'requiere_masa' => false,
        ),
        4 => 
        array (
          'nombre' => 'Chocolate con Leche',
          'precio' => 1.5,
          'requiere_masa' => false,
        ),
        5 => 
        array (
          'nombre' => 'Leche Caliente',
          'precio' => 1.5,
          'requiere_masa' => false,
        ),
        6 => 
        array (
          'nombre' => 'Té de Manzanilla',
          'precio' => 1.5,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Cafés e Infusiones',
        ),
      ),
    ),
    17 => 
    array (
      'nombre' => 'Otros',
      'descripcion' => 'Otras bebidas.',
      'area' => 'BAR',
      'productos' => 
      array (
        0 => 
        array (
          'nombre' => 'Agua Mineral',
          'precio' => 1.25,
          'requiere_masa' => false,
        ),
        1 => 
        array (
          'nombre' => 'Agua Mineral con Limón',
          'precio' => 2.0,
          'requiere_masa' => false,
        ),
        2 => 
        array (
          'nombre' => 'Botella con Agua',
          'precio' => 1.0,
          'requiere_masa' => false,
        ),
        3 => 
        array (
          'nombre' => 'Gaseosa',
          'precio' => 1.25,
          'requiere_masa' => false,
        ),
      ),
      'categorias' => 
      array (
        0 => 
        array (
          'nombre' => 'Varios',
        ),
      ),
    ),
  ),
  'combos' => 
  array (
  ),
);
