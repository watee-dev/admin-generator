<?php

namespace Brackets\AdminGenerator\Tests\Feature\Appenders;

use Brackets\AdminGenerator\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class LangTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    function auto_generated_lang_append(){
        $filePath = resource_path('lang/en/admin.php');

        $this->artisan('admin:generate:lang', [
            'table_name' => 'categories'
        ]);

        $this->assertStringStartsWith('<?php

return [
    \'category\' => [
        \'actions\' => [
            \'index\' => \'Categories\',
            \'create\' => \'New Category\',
            \'edit\' => \'Edit :name\',
        ],

        \'columns\' => [
            \'title\' => "Title",
            
        ],
    ],

    // Do not delete me :) I\'m used for auto-generation
];', File::get($filePath));
    }


    /** @test */
    function namespaced_model_lang_append(){
        $filePath = resource_path('lang/en/admin.php');

        $this->artisan('admin:generate:lang', [
            'table_name' => 'categories',
            '--model-name' => 'Billing\\CategOry',
        ]);

        $this->assertStringStartsWith('<?php

return [
    \'billing_categ-ory\' => [
        \'actions\' => [
            \'index\' => \'CategOries\',
            \'create\' => \'New CategOry\',
            \'edit\' => \'Edit :name\',
        ],

        \'columns\' => [
            \'title\' => "Title",
            
        ],
    ],

    // Do not delete me :) I\'m used for auto-generation
];', File::get($filePath));
    }

}