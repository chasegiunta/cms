<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftunit\validators;

use Codeception\Test\Unit;
use craft\helpers\ArrayHelper;
use craft\test\mockclasses\models\ExampleModel;
use craft\validators\LanguageValidator;
use UnitTester;
use yii\base\NotSupportedException;

/**
 * Class LanguageValidatorTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.1
 */
class LanguageValidatorTest extends Unit
{
    /**
     * @var UnitTester $tester
     */
    protected $tester;

    /**
     * @var LanguageValidator $languageValidator
     */
    protected $languageValidator;

    public function _before()
    {
        parent::_before();
        $this->languageValidator = new LanguageValidator();
    }

    /**
     * @param $result
     * @param $input
     * @param bool $onlySiteLangs
     * @throws NotSupportedException
     * @dataProvider validateValueData
     */
    public function testValidateValue($result, $input, $onlySiteLangs = true)
    {
        $this->tester->mockCraftMethods('i18n', ['getSiteLocaleIds' => ['nl', 'en-US']]);
        $this->languageValidator->onlySiteLanguages = $onlySiteLangs;
        $validated = $this->languageValidator->validateValue($input);

        $this->assertSame($result, $validated);
    }
    public function validateValueData(): array
    {
        return [
            [['{value} is not a valid site language.', []], 'nolang'],
            [null, 'en-US'],
            [null, 'nl'],
            [['{value} is not a valid site language.', []], 'de'],
            [null, 'de', false],
            [['{value} is not a valid site language.', []], 'nolang', false]
        ];
    }

    /**
     * @param $mustValidate
     * @param $input
     * @param bool $onlySiteLocalIds
     * @dataProvider validateAttributeData
     */
    public function testValidateAtrribute($mustValidate, $input, $onlySiteLocalIds = true)
    {
        $this->tester->mockCraftMethods('i18n', ['getSiteLocaleIds' => ['nl', 'en-US']]);

        $model = new ExampleModel(['exampleParam' => $input]);

        $this->languageValidator->onlySiteLanguages = $onlySiteLocalIds;
        $this->languageValidator->validateAttribute($model, 'exampleParam');

        if (!$mustValidate) {
            $this->assertArrayHasKey('exampleParam', $model->getErrors());
        } else {
            $this->assertSame([], $model->getErrors());
        }
    }
    public function validateAttributeData(): array
    {
        $returnArray = [];
        foreach ($this->validateValueData() as $item) {
            $mustValidate = true;
            if (!empty($item[0])) {
                $mustValidate = false;
            }

            $lang = $item[1];
            $requireOnlySite = true;
            if (isset($item[2]) && $item[2] === false) {
                $requireOnlySite = false;
            }

            $returnArray[] = [
                $mustValidate,
                $lang,
                $requireOnlySite
            ];
        }

        return ArrayHelper::merge($returnArray, [
            [true, 'en-US'],
            [true, 'EN-US'],
            [false, 'notalang'],
        ]);
    }

}