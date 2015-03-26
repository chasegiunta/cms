<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\records;

use craft\app\db\ActiveRecord;
use craft\app\enums\AttributeType;

/**
 * Class AssetTransform record.
 *
 * @var integer $id ID
 * @var string $name Name
 * @var string $handle Handle
 * @var string $mode Mode
 * @var string $position Position
 * @var integer $height Height
 * @var integer $width Width
 * @var string $format Format
 * @var integer $quality Quality
 * @var \DateTime $dimensionChangeTime Dimension change time

 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class AssetTransform extends ActiveRecord
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['handle'], 'craft\\app\\validators\\Handle', 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']],
			[['mode'], 'in', 'range' => ['stretch', 'fit', 'crop']],
			[['position'], 'in', 'range' => ['top-left', 'top-center', 'top-right', 'center-left', 'center-center', 'center-right', 'bottom-left', 'bottom-center', 'bottom-right']],
			[['height'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true],
			[['width'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true],
			[['quality'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true],
			[['dimensionChangeTime'], 'craft\\app\\validators\\DateTime'],
			[['name', 'handle'], 'unique'],
			[['name', 'handle', 'mode', 'position'], 'required'],
			[['handle'], 'string', 'max' => 255],
		];
	}

	/**
	 * @inheritdoc
	 *
	 * @return string
	 */
	public static function tableName()
	{
		return '{{%assettransforms}}';
	}
}
