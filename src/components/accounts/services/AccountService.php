<?php
namespace Blocks;

/**
 *
 */
class AccountService extends BaseApplicationComponent
{
	public $accountVerificationPath = 'verify';

	private $_currentUser;

	/**
	 * Populates a user model.
	 *
	 * @param array|UserRecord $attributes
	 * @return UserModel
	 */
	public function populateUser($attributes)
	{
		$user = UserModel::populateModel($attributes);

		// Is the user in cooldown mode, and are they past their window?
		if ($user->status == UserStatus::Locked)
		{
			$cooldownDuration = blx()->config->cooldownDuration;

			if ($cooldownDuration)
			{
				if (!$user->getRemainingCooldownTime())
				{
					$this->activateUser($user);
				}
			}
		}

		return $user;
	}

	/**
	 * Gets a user by their ID.
	 *
	 * @param $id
	 * @return UserRecord
	 */
	public function getUserById($id)
	{
		$userRecord = UserRecord::model()->findById($id);

		if ($userRecord)
		{
			return $this->populateUser($userRecord);
		}
	}

	/**
	 * Gets a user by their username or email.
	 *
	 * @param string $usernameOrEmail
	 * @return UserModel
	 */
	public function getUserByUsernameOrEmail($usernameOrEmail)
	{
		$userRecord = UserRecord::model()->find(array(
			'condition' => 'username=:usernameOrEmail OR email=:usernameOrEmail',
			'params' => array(':usernameOrEmail' => $usernameOrEmail),
		));

		if ($userRecord)
		{
			return $this->populateUser($userRecord);
		}
	}

	/**
	 * Gets a user by a verification code.
	 *
	 * @param string $code
	 * @return UserModel
	 */
	public function getUserByVerificationCode($code)
	{
		if ($code)
		{
			$date = new DateTime();
			$duration = new DateInterval(blx()->config->verificationCodeDuration);
			$date->sub($duration);

			$userRecord = UserRecord::model()->find(
				'verificationCode = :code and verificationCodeIssuedDate > :date',
				array(':code' => $code, ':date' => $date->getTimestamp())
			);

			if ($userRecord)
			{
				return $this->populateUser($userRecord);
			}
		}
	}

	/**
	 * Gets the currently logged-in user.
	 *
	 * @return UserModel
	 */
	public function getCurrentUser()
	{
		// Is a user actually logged in?
		if (blx()->isInstalled() && !empty(blx()->user))
		{
			if (!isset($this->_currentUser))
			{
				$userId = blx()->user->getId();
				$userRecord = UserRecord::model()->findById($userId);

				if ($userRecord)
				{
					$this->_currentUser = $this->populateUser($userRecord);
				}
				else
				{
					$this->_currentUser = null;
				}
			}

			return $this->_currentUser;
		}
	}

	/**
	 * Returns whether the current user is an admin.
	 *
	 * @return bool
	 */
	public function isAdmin()
	{
		$user = $this->getCurrentUser();

		if ($user)
		{
			return $user->admin;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Saves a user, or registers a new one.
	 *
	 * @param UserModel $user
	 * @return bool
	 */
	public function saveUser(UserModel $user)
	{
		if ($user->id)
		{
			$userRecord = $this->_getUserRecordById($user->id);
		}
		else
		{
			$userRecord = new UserRecord();
		}

		$userRecord->username = $user->username;
		$userRecord->firstName = $user->firstName;
		$userRecord->lastName = $user->lastName;
		$userRecord->email = $user->email;
		$userRecord->emailFormat = $user->emailFormat;
		$userRecord->admin = $user->admin;
		$userRecord->passwordResetRequired = $user->passwordResetRequired;
		$userRecord->language = $user->language;

		if ($user->newPassword)
		{
			$this->_setPasswordOnUserRecord($user, $userRecord);
		}

		if ($userRecord->validate() && !$user->hasErrors())
		{
			if ($user->verificationRequired)
			{
				$userRecord->status = $user->status = UserStatus::Pending;
				$this->_setVerificationCodeOnUserRecord($userRecord);
			}

			$userRecord->save();

			$user->id = $userRecord->id;

			if ($user->verificationRequired)
			{
				blx()->email->sendEmailByKey($user, 'verify_email', array(
					'link' => $this->_getVerifyAccountUrl($userRecord)
				));
			}

			return true;
		}
		else
		{
			$user->addErrors($userRecord->getErrors());
			return false;
		}
	}

	/**
	 * Sends a verification email
	 */
	public function sendVerificationEmail(UserModel $user)
	{
		$userRecord = $this->_getUserRecordById($user->id);
		$this->_setVerificationCodeOnUserRecord($userRecord);
		$userRecord->save();

		return blx()->email->sendEmailByKey($user, 'verify_email', array(
			'link' => $this->_getVerifyAccountUrl($userRecord)
		));
	}

	/**
	 * Sends a "forgot password" email.
	 *
	 * @param UserModel $user
	 * @return bool
	 */
	public function sendForgotPasswordEmail(UserModel $user)
	{
		$userRecord = $this->_getUserRecordById($user->id);
		$this->_setVerificationCodeOnUserRecord($userRecord);
		$userRecord->save();

		return blx()->email->sendEmailByKey($user, 'forgot_password', array(
			'link' => $this->_getVerifyAccountUrl($userRecord)
		));
	}

	/**
	 * Sets a user record up for a new verification code without saving it.
	 *
	 * @access private
	 * @param UserRecord $userRecord
	 */
	private function _setVerificationCodeOnUserRecord(UserRecord $userRecord)
	{
		$userRecord->verificationCode = StringHelper::UUID();
		$userRecord->verificationCodeIssuedDate = new DateTime();
	}

	/**
	 * Gets the account verification URL for a user record.
	 *
	 * @access private
	 * @param UserRecord $userRecord
	 * @return string
	 * @throws Exception
	 */
	private function _getVerifyAccountUrl(UserRecord $userRecord)
	{
		if ($userRecord->verificationCode)
		{
			return UrlHelper::getUrl($this->accountVerificationPath, array(
				'code' => $userRecord->verificationCode
			));
		}
		else
		{
			throw new Exception(Blocks::t('This user doesn’t have a verification code set.'));
		}
	}

	/**
	 * Changes a user's password.
	 *
	 * @param UserModel $user
	 * @return bool
	 */
	public function changePassword(UserModel $user)
	{
		$userRecord = $this->_getUserRecordById($user->id);

		if ($this->_setPasswordOnUserRecord($user, $userRecord))
		{
			$userRecord->save();
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Sets a user record up for a new password without saving it.
	 *
	 * @access private
	 * @param UserModel $user
	 * @param UserRecord $userRecord
	 * @return bool
	 */
	private function _setPasswordOnUserRecord(UserModel $user, UserRecord $userRecord)
	{
		// Validate the password first
		$passwordModel = new PasswordModel();
		$passwordModel->password = $user->newPassword;

		if ($passwordModel->validate())
		{
			$hashAndType = blx()->security->hashPassword($user->newPassword);

			$userRecord->password = $user->password = $hashAndType['hash'];
			$userRecord->encType = $user->encType = $hashAndType['encType'];
			$userRecord->status = $user->status = UserStatus::Active;
			$userRecord->invalidLoginWindowStart = null;
			$userRecord->invalidLoginCount = $user->invalidLoginCount = null;
			$userRecord->verificationCode = null;
			$userRecord->verificationCodeIssuedDate = null;
			$userRecord->passwordResetRequired = $user->passwordResetRequired = false;
			$userRecord->lastPasswordChangeDate = $user->lastPasswordChangeDate = DateTimeHelper::currentTime();

			$user->newPassword = null;

			return true;
		}
		else
		{
			$user->addError('newPassword', Blocks::t('Invalid password.'));
			return false;
		}
	}

	/**
	 * Handles a successful login for a user.
	 *
	 * @param UserModel $user
	 * @param string $authSessionToken
	 * @return bool
	 */
	public function handleSuccessfulLogin(UserModel $user, $authSessionToken)
	{
		$userRecord = $this->_getUserRecordById($user->id);

		$userRecord->authSessionToken = $authSessionToken;
		$userRecord->lastLoginDate = $user->lastLoginDate = DateTimeHelper::currentTime();
		$userRecord->lastLoginAttemptIPAddress = blx()->request->getUserHostAddress();
		$userRecord->invalidLoginWindowStart = null;
		$userRecord->invalidLoginCount = $user->invalidLoginCount = null;
		$userRecord->verificationCode = null;
		$userRecord->verificationCodeIssuedDate = null;

		return $userRecord->save();
	}

	/**
	 * Handles an invalid login for a user.
	 *
	 * @param UserModel $user
	 * @return bool
	 */
	public function handleInvalidLogin(UserModel $user)
	{
		$userRecord = $this->_getUserRecordById($user->id);
		$currentTime = DateTimeHelper::currentTime();

		$userRecord->lastInvalidLoginDate = $user->lastInvalidLoginDate = $currentTime;
		$userRecord->lastLoginAttemptIPAddress = blx()->request->getUserHostAddress();

		if ($this->_isUserInsideInvalidLoginWindow($userRecord))
		{
			$userRecord->invalidLoginCount++;

			// Was that one bad password too many?
			if ($userRecord->invalidLoginCount >= blx()->config->maxInvalidLogins)
			{
				$userRecord->status = $user->status = UserStatus::Locked;
				$userRecord->invalidLoginCount = null;
				$userRecord->invalidLoginWindowStart = null;
				$userRecord->lockoutDate = $user->lockoutDate = $currentTime;
			}
		}
		else
		{
			// Start the invalid login window and counter
			$userRecord->invalidLoginWindowStart = $currentTime;
			$userRecord->invalidLoginCount = 1;
		}

		// Update the counter on the user model
		$user->invalidLoginCount = $userRecord->invalidLoginCount;

		return $userRecord->save();
	}


	/**
	 * Determines if a user is within their invalid login window.
	 *
	 * @param UserRecord $userRecord
	 * @return bool
	 */
	private function _isUserInsideInvalidLoginWindow(UserRecord $userRecord)
	{
		if ($userRecord->invalidLoginWindowStart)
		{
			$duration = new DateInterval(blx()->config->invalidLoginWindowDuration);
			$end = $userRecord->invalidLoginWindowStart->add($duration);
			return ($end >= new DateTime());
		}
		else
		{
			return false;
		}
	}

	/**
	 * Activates a user, bypassing email verification.
	 *
	 * @param UserModel $user
	 * @return bool
	 */
	public function activateUser(UserModel $user)
	{
		$userRecord = $this->_getUserRecordById($user->id);

		$userRecord->status = $user->status = UserStatus::Active;
		$userRecord->verificationCode = null;
		$userRecord->verificationCodeIssuedDate = null;

		return $userRecord->save();
	}

	/**
	 * Unlocks a user, bypassing the cooldown phase.
	 *
	 * @param UserModel $user
	 * @return bool
	 */
	public function unlockUser(UserModel $user)
	{
		$userRecord = $this->_getUserRecordById($user->id);

		$userRecord->status = $user->status = UserStatus::Active;
		$userRecord->invalidLoginCount = $user->invalidLoginCount = null;
		$userRecord->invalidLoginWindowStart = null;

		return $userRecord->save();
	}

	/**
	 * Suspends a user.
	 *
	 * @param UserModel $user
	 * @return bool
	 */
	public function suspendUser(UserModel $user)
	{
		$userRecord = $this->_getUserRecordById($user->id);

		$userRecord->status = $user->status = UserStatus::Suspended;

		return $userRecord->save();
	}

	/**
	 * Unsuspends a user.
	 *
	 * @param UserModel $user
	 * @return bool
	 */
	public function unsuspendUser(UserModel $user)
	{
		$userRecord = $this->_getUserRecordById($user->id);

		$userRecord->status = $user->status = UserStatus::Active;

		return $userRecord->save();
	}

	/**
	 * Deletes a user.
	 *
	 * @param UserModel $user
	 * @return bool
	 */
	public function deleteUser(UserModel $user)
	{
		$userRecord = $this->_getUserRecordById($user->id);

		$userRecord->status = $user->status = UserStatus::Archived;
		$userRecord->archivedUsername = $user->username;
		$userRecord->archivedEmail = $user->email;
		$userRecord->username = '';
		$userRecord->email = '';

		return $userRecord->save(false);
	}

	/**
	 * Gets a user record by its ID.
	 *
	 * @access private
	 * @param int $userId
	 * @return UserRecord
	 * @throws Exception
	 */
	private function _getUserRecordById($userId)
	{
		$userRecord = UserRecord::model()->findById($userId);

		if (!$userRecord)
		{
			throw new Exception(Blocks::t('No user exists with the ID “{id}”', array('id' => $userId)));
		}

		return $userRecord;
	}
}
