<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\records;

use yii\db\ActiveQueryInterface;
use craft\app\db\ActiveRecord;
use craft\app\enums\AttributeType;
use craft\app\enums\ColumnType;

/**
 * Class Structure record.
 *
 * @var integer $id ID
 * @var integer $maxLevels Max levels
 * @var ActiveQueryInterface $elements Elements

 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Structure extends ActiveRecord
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['maxLevels'], 'number', 'min' => 1, 'max' => 65535, 'integerOnly' => true],
		];
	}

	/**
	 * @inheritdoc
	 *
	 * @return string
	 */
	public static function tableName()
	{
		return '{{%structures}}';
	}

	/**
	 * Returns the structure’s elements.
	 *
	 * @return \yii\db\ActiveQueryInterface The relational query object.
	 */
	public function getElements()
	{
		return $this->hasMany(StructureElement::className(), ['structureId' => 'id']);
	}
}
