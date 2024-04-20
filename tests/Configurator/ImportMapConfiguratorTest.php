<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Flex\Tests\Configurator;

use Composer\Composer;
use Composer\IO\IOInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Flex\Configurator\ImportMapConfigurator;
use Symfony\Flex\Lock;
use Symfony\Flex\Options;
use Symfony\Flex\Recipe;
use Symfony\Flex\Update\RecipeUpdate;

class ImportMapConfiguratorTest extends TestCase
{
    private const IMPORT_MAP_FILE = FLEX_TEST_DIR.'/importmap.php';

    protected function setUp(): void
    {
        @mkdir(FLEX_TEST_DIR);
    }

    protected function tearDown(): void
    {
        @unlink(self::IMPORT_MAP_FILE);
    }

    /**
     * @dataProvider provideConfigure
     */
    public function testConfigure(?string $existingImportMap, array $config, string $expectedImportMap)
    {
        if (null !== $existingImportMap) {
            file_put_contents(self::IMPORT_MAP_FILE, $existingImportMap);
        }

        $configurator = new ImportMapConfigurator(
            $this->getMockBuilder(Composer::class)->getMock(),
            $this->getMockBuilder(IOInterface::class)->getMock(),
            new Options(['root-dir' => FLEX_TEST_DIR])
        );

        $recipe = $this->getMockBuilder(Recipe::class)->disableOriginalConstructor()->getMock();
        $lock = $this->getMockBuilder(Lock::class)->disableOriginalConstructor()->getMock();

        $configurator->configure($recipe, $config, $lock);
        $this->assertSame($expectedImportMap, file_get_contents(self::IMPORT_MAP_FILE));
    }

    public static function provideConfigure(): iterable
    {
        yield 'new importmap' => [
            null,
            [
                'bootstrap/dist/css/bootstrap.min.css' => [
                    'version' => '5.3.2',
                    'type' => 'css',
                ],
                'tom-select' => [
                    'version' => '2.3.1',
                ],
                'clipboard' => [
                    'version' => '2.0.11',
                ],
            ],
            <<<EOF
<?php

return [
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.2',
        'type' => 'css',
    ],
    'tom-select' => [
        'version' => '2.3.1',
    ],
    'clipboard' => [
        'version' => '2.0.11',
    ],
];

EOF
        ];

        yield 'existing importmap' => [
            <<<EOF
<?php

return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'highlight.js/lib/core' => [
        'version' => '11.9.0',
    ],
];
EOF,
            [
                'bootstrap' => [
                    'version' => '5.3.0'
                ],
                'jquery' => [
                    'version' => '3.6.0'
                ],
            ],
            <<<EOF
<?php

return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'highlight.js/lib/core' => [
        'version' => '11.9.0',
    ],
    'bootstrap' => [
        'version' => '5.3.0',
    ],
    'jquery' => [
        'version' => '3.6.0',
    ],
];

EOF
        ];

        yield 'existing importmap with duplicates' => [
            <<<EOF
<?php

return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'bootstrap' => [
        'version' => '4.0.0'
    ],
    'jquery' => [
        'version' => '2.1.3'
    ],
];

EOF,
            [
                'bootstrap' => [
                    'version' => '5.3.0'
                ],
                'jquery' => [
                    'version' => '3.6.0'
                ],
            ],
            <<<EOF
<?php

return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'bootstrap' => [
        'version' => '4.0.0',
    ],
    'jquery' => [
        'version' => '2.1.3',
    ],
];

EOF
        ];
    }

    public function testUnconfigure()
    {
        file_put_contents(self::IMPORT_MAP_FILE, <<<EOF
<?php

return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'bootstrap' => [
        'version' => '5.3.0',
    ],
    'jquery' => [
        'version' => '3.6.0',
    ],
];
EOF
        );

        $configurator = new ImportMapConfigurator(
            $this->getMockBuilder(Composer::class)->getMock(),
            $this->getMockBuilder(IOInterface::class)->getMock(),
            new Options(['root-dir' => FLEX_TEST_DIR])
        );

        $recipe = $this->getMockBuilder(Recipe::class)->disableOriginalConstructor()->getMock();
        $lock = $this->getMockBuilder(Lock::class)->disableOriginalConstructor()->getMock();

        $configurator->unconfigure($recipe, [
            'jquery' => [
                'version' => '3.6.0'
            ],
        ], $lock);
        $this->assertEquals(<<<EOF
<?php

return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'bootstrap' => [
        'version' => '5.3.0',
    ],
];

EOF
            , file_get_contents(self::IMPORT_MAP_FILE));
    }

    public function testUpdate()
    {
        $configurator = new ImportMapConfigurator(
            $this->createMock(Composer::class),
            $this->createMock(IOInterface::class),
            new Options(['config-dir' => 'config', 'root-dir' => FLEX_TEST_DIR])
        );

        $recipeUpdate = new RecipeUpdate(
            $this->createMock(Recipe::class),
            $this->createMock(Recipe::class),
            $this->createMock(Lock::class),
            FLEX_TEST_DIR
        );

        file_put_contents(self::IMPORT_MAP_FILE, <<<EOF
<?php

return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'lodash' => [
        'version' => '4.17.21',
    ],
    'bootstrap' => [
        'version' => '5.3.0',
    ],
    'jquery' => [
        'version' => '3.6.0',
    ],
];
EOF
        );

        $configurator->update(
            $recipeUpdate,
            [
                'bootstrap' => [
                    'version' => '5.3.0',
                ],
                'jquery' => [
                    'version' => '3.6.0'
                ],
            ],
            [
                'bootstrap/dist/css/bootstrap.min.css' => [
                    'version' => '5.3.0',
                    'type' => 'css',
                ],
                'jquery' => [
                    'version' => '4.2.2'
                ],
                'highlight.js/lib/core' => [
                    'version' => '11.9.0',
                ],
            ],
        );

        $this->assertSame([self::IMPORT_MAP_FILE => <<<EOF
<?php

return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'lodash' => [
        'version' => '4.17.21',
    ],
    'bootstrap' => [
        'version' => '5.3.0',
    ],
    'jquery' => [
        'version' => '3.6.0',
    ],
];

EOF
        ], $recipeUpdate->getOriginalFiles());

        // bootstrap dropped, jquery updated, highlight.js/lib/core added
        $this->assertSame([self::IMPORT_MAP_FILE => <<<EOF
<?php

return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'lodash' => [
        'version' => '4.17.21',
    ],
    'jquery' => [
        'version' => '4.2.2',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.0',
        'type' => 'css',
    ],
    'highlight.js/lib/core' => [
        'version' => '11.9.0',
    ],
];

EOF
        ], $recipeUpdate->getNewFiles());
    }
}
