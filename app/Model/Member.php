<?php

	App::uses('AppModel', 'Model');
	App::uses('Status', 'Model');
	App::uses('InvalidStatusException', 'Error/Exception');
	App::uses('NotAuthorizedException', 'Error/Exception');
	App::uses('String', 'Utility');

	/**
	 * Model for all member data
	 *
	 *
	 * @package       app.Model
	 */
	class Member extends AppModel 
	{
		const MIN_PASSWORD_LENGTH = 6; //!< The minimum length passwords must be.
		const MIN_USERNAME_LENGTH = 3; //!< The minimum length usernames must be.
		const MAX_USERNAME_LENGTH = 30; //!< The maximum length usernames can be.

		public $primaryKey = 'member_id'; //!< Specify the primary key, since we don't use the default.

		//! We belong to both the Status and Account models.
		/*! 
			Status should be joined on an inner join as it makes no sense to have no status.
			Account should be likewise, but isn't because we have to play nice with the existing data.
		*/
		public $belongsTo =  array(
			'Status' => array(
					'className' => 'Status',
					'foreignKey' => 'member_status',
					'type' => 'inner'
			),
			'Account' => array(
					'className' => 'Account',
					'foreignKey' => 'account_id',
			),
		);

		//! We have a Pin.
		/*!
			Pin is set to be dependant so it will be deleted when the Member is.
		*/
		public $hasOne = array(
	        'Pin' => array(
	            'className' => 'Pin',
	            'dependent' => true
	        ),
	    );

		//! We have many StatusUpdate.
		/*!
			We only (normally) care about the most recent Status Update.
		*/
	    public $hasMany = array(
	    	'StatusUpdate' => array(
	    		'order' => 'StatusUpdate.timestamp DESC',
	    		'limit'	=> '1',	
	    	),
	    );

	    //! We have and belong to many Group.
	    /*!
	    	Group is set to be unique as it is impossible for the Member to be in the same Group twice.
	    	We also specify a model to use as the 'with' model so that we can add methods to it.
	    */
		public $hasAndBelongsToMany = array(
	        'Group' =>
	            array(
	                'className' => 'Group',
	                'joinTable' => 'member_group',
	                'foreignKey' => 'member_id',
	                'associationForeignKey' => 'grp_id',
	                'unique' => true,
	                'with' => 'GroupsMember',				
	            ),
	    );

		//! Validation rules.
		/*!
			Name must not be empty.
			Email must be a valid email (and not empty).
			Password must not be empty and have a length equal or greater than the Member::MIN_PASSWORD_LENGTH.
			Password Confirm must not be empty, have a length equal or greater than the Member::MIN_PASSWORD_LENGTH and it's contents much match that of Password.
			Username must not be empty, be unique (in the database), only contain alpha-numeric characters, and be between Member::MIN_USERNAME_LENGTH and Member::MAX_USERNAME_LENGTH characters long.
			Member Status must be in the list of valid statuses.
			Account id must be numeric.
			Address 1 must not be empty.
			Address City must not be empty.
			Address Postcode must not be empty.
			Contact Number must not be empty.
			No further validation is performed on the Address and Contact Number fields as a member admin has to check these during membership registration.
		*/
		public $validate = array(
	        'name' => array(
	            'rule' => 'notEmpty'
	        ),
	        'email' => array(
	        	'email'
	        ),
	        'password' => array(
	        	'noEmpty' => array(
	            	'rule' => 'notEmpty',
	            	'message' => 'This field cannot be left blank'
	            ),
	        	'minLen' => array(
	        		'rule' => array('minLength', self::MIN_PASSWORD_LENGTH),
            		'message' => 'Password too short',
            	),
	        ),
	        'password_confirm' => array(
	        	'noEmpty' => array(
	            	'rule' => 'notEmpty',
	            	'message' => 'This field cannot be left blank'
	            ),
	            'minLen' => array(
	        		'rule' => array('minLength', self::MIN_PASSWORD_LENGTH),
            		'message' => 'Password too short',
            	),
	        	'matchNewPassword' => array(
	            	'rule' => array( 'passwordConfirmMatchesPassword' ),
	            	'message' => 'Passwords don\'t match',
	            ),
	        ),
	        'username' => array(
	        	'noEmpty' => array(
	            	'rule' => 'notEmpty',
	            	'message' => 'This field cannot be left blank'
	            ),
	        	'mustbeUnique' => array(
	            	'rule' => array( 'checkUniqueUsername' ),
	            	'message' => 'That username is already taken',
	            ),
	            'alphaNumeric' => array(
	                'rule'     => 'alphaNumeric',
	                'message'  => 'Aplha-numeric characters only'
	            ),
	            'between' => array(
	                'rule'    => array('between', self::MIN_USERNAME_LENGTH, self::MAX_USERNAME_LENGTH),
	                'message' => 'Between 3 to 30 characters'
	            ),

	        ),
	        'account_id' => array(
	        	'rule' => 'numeric',
	        ),
	        'member_status' => array(
	        	'rule' => array(
	        		'inList', array(
	        			Status::PROSPECTIVE_MEMBER,
	        			Status::PRE_MEMBER_1,
	        			Status::PRE_MEMBER_2,
	        			Status::PRE_MEMBER_3,
	        			Status::CURRENT_MEMBER,
	        			Status::EX_MEMBER,
	        		),
	        	),
	        ),
	        'address_1' => array(
	            'rule' => 'notEmpty'
	        ),
	        'address_city' => array(
	            'rule' => 'notEmpty'
	        ),
	        'address_postcode' => array(
	            'rule' => 'notEmpty'
	        ),
	        'contact_number' => array(
	            'rule' => 'notEmpty'
	        ),
	    );

		//! Use the KrbAuth behaviour for setting passwords and the like.
		public $actsAs = array('KrbAuth');

		//! Validation function to see if the user-supplied password and password confirmation match.
		/*!
			@param array $check The password to be validated.
			@retval bool True if the supplied password values match, otherwise false.
		*/
	    public function passwordConfirmMatchesPassword($check)
		{
			return $this->data['Member']['password'] === $check['password_confirm'];
		}

		//! Validation function to see if the user-supplied username is already taken.
		/*!
			@param array $check The username to check.
			@retval bool True if the supplied username exists in the database (case-insensitive) registered to a different user, otherwise false.
		*/
		public function checkUniqueUsername($check)
		{
			$lowercaseUsername = strtolower($check['username']);
			$records = $this->find('all', 
				array(  'fields' => array('Member.username'),
					'conditions' => array( 
						'Member.username LIKE' => $lowercaseUsername,
						'Member.member_id NOT' => $this->data['Member']['member_id'],
					) 
				)
			);

			foreach ($records as $record) 
			{
				if(strtolower($record['Member']['username']) == $lowercaseUsername)
				{
					return false;
				}
			}
			return true;
		}

		//! Validation function to see if the user-supplied email matches what's in the database.
		/*!
			@param array $check The email to check.
			@retval bool True if the supplied email value matches the database, otherwise false.
			@sa Member::addEmailMustMatch()
			@sa Member::removeEmailMustMatch()
		*/
		public function checkEmailMatch($check)
		{
			$ourEmail = $this->find('first', array('fields' => array('Member.email'), 'conditions' => array('Member.member_id' => $this->data['Member']['member_id'])));
			return strcasecmp($ourEmail['Member']['email'], $check['email']) == 0;
		}

		//! Actions to perform before saving any data
		/*!
			@param array $options Any options that were passed to the Save method
			@sa http://book.cakephp.org/2.0/en/models/callback-methods.html#beforesave
		*/
		public function beforeSave($options = array()) 
		{
			# Must never ever ever alter the balance
			unset( $this->data['Member']['balance'] );

			return true;
		}

		//! Add an extra validation rule to the e-mail field stating that the user supplied e-mail must match what's in the database.
		/*!
			@sa Member::checkEmailMatch()
			@sa Member::removeEmailMustMatch()
		*/
		public function addEmailMustMatch()
		{
			$this->validator()->add('email', 'emailMustMatch', array( 'rule' => array( 'checkEmailMatch' ), 'message' => 'Incorrect email used' ));
		}

		//! Remove the 'e-mail must match' validation rule.
		/*!
			@sa Member::checkEmailMatch()
			@sa Member::addEmailMustMatch()
		*/
		public function removeEmailMustMatch()
		{
			$this->validator()->remove('email', 'emailMustMatch');
		}

		//! Find how many members have a certain Status.
		/*!
			@param int $status_id The id of the Status record to check.
			@retval int The number of member records that belong to the Status.
		*/
		public function getCountForStatus($status_id)
		{
			return $this->find( 'count', array( 'conditions' => array( $this->belongsTo['Status']['foreignKey'] => $status_id ) ) );
		}

		//! Find out how many member records exist in the database.
		/*!
			@retval int The number of member records in the database.
		*/
		public function getCount()
		{
			return $this->find( 'count' );
		}

		//! Find out if we have record of a Member with a specific e-mail address.
		/*!
			@param string $email E-mail address to check.
			@retval bool True if there is a Member with this e-mail, false otherwise.
		*/
		public function doesMemberExistWithEmail($email)
		{
			return $this->find( 'count', array( 'conditions' => array( 'Member.email' => strtolower($email) ) ) ) > 0;
		}

		//! Get a summary of the member records for a specific member.
		/*!
			@retval array A summary of the data for a specific member.
			@sa Member::_getMemberSummary()
		*/
		public function getMemberSummaryForMember($memberId)
		{
			$memberList = $this->_getMemberSummary(false, array('Member.member_id' => $memberId));
			if(!empty($memberList))
			{
				return $memberList[0];
			}
			return array();
		}

		//! Get a summary of the member records for all members.
		/*!
			@retval array A summary of the data of all members.
			@sa Member::_getMemberSummary()
		*/
		public function getMemberSummaryAll($paginate)
		{
			return $this->_getMemberSummary($paginate);
		}

		//! Get a summary of the member records for all members.
		/*!
			@param int $statusId Retrieve information about members who have this status.
			@retval array A summary of the data of all members of a status.
			@sa Member::_getMemberSummary()
		*/
		public function getMemberSummaryForStatus($paginate, $statusId)
		{
			return $this->_getMemberSummary($paginate, array( 'Member.member_status' => $statusId ) );
		}

		//! Get a summary of the member records for all member records where their name, email, username or handle is similar to the keyword.
		/*!
			@param string $keyword Term to search for.
			@retval array A summary of the data of all members who match the query.
			@sa Member::_getMemberSummary()
		*/
		public function getMemberSummaryForSearchQuery($paginate, $keyword)
		{
			return $this->_getMemberSummary( $paginate,
				array( 'OR' => 
					array(
						"Member.name Like'%$keyword%'", 
						"Member.email Like'%$keyword%'",
						"Member.username Like'%$keyword%'",
						"Member.handle Like'%$keyword%'",
					)
				)
			);
		}

		//! Format member information into a nicer arrangement.
		/*!
			@param $info The info to format, usually retrieved from Member::_getMemberSummary.
			@retval array An array of member information, formatted so that nothing needs to know database rows.
			@sa Member::_getMemberSummary
		*/
		public function formatMemberInfo($info)
		{
			/*
	    	    Data should be presented to the view in an array like so:
	    			[n] => 
	    				[id] => member id
	    				[name] => member name
	    				[email] => member email
	    				[groups] => 
	    					[n] =>
	    						[id] => group id
	    						[description] => group description
	    				[status] => 
	    					[id] => status id
	    					[name] => name of the status
	    	*/
			$formattedInfo = array();
	    	foreach ($info as $member) 
	    	{
	    		$id = Hash::get($member, 'Member.member_id');
	    		$name = Hash::get($member, 'Member.name');
	    		$email = Hash::get($member, 'Member.email');

	    		$status = array(
	    			'id' => Hash::get($member, 'Status.status_id'),
	    			'name' => Hash::get($member, 'Status.title'),
	    		);

	    		$groups = array();
	    		foreach($member['Group'] as $group)
	    		{
	    			array_push($groups,
		    			array(
		    				'id' => Hash::get($group, 'grp_id'),
		    				'description' => Hash::get($group, 'grp_description'),
		    			)
		    		);
	    		}

	    		array_push($formattedInfo,
	    			array(
	    				'id' => $id,
	    				'name' => $name,
	    				'email' => $email,
	    				'groups' => $groups,
	    				'status' => $status,
	    			)
	    		);
	    	}

	    	return $formattedInfo;
		}

		//! Create a member info array for a new member.
		/*!
			@param string $email The e-mail address for the new member.
			@retval array An array of member info suitable for saving.
		*/
		public function createNewMemberInfo($email)
		{
			return array(
				'Member' => array(
					'email' => $email,
					'member_status' => Status::PROSPECTIVE_MEMBER,
				),
			);
		}

		//! Get the Status for a member, may hit the database.
		/*!
			@param mixed $memberData If array, assumed to be an array of member info in the same format that is returned from database queries, otherwise assumed to be a member id.
			@retval int The status for the member, or 0 if status could not be found.
		*/
		public function getIdForMember($memberData)
		{
			if(!isset($memberData))
			{
				return 0;
			}

			if(is_array($memberData))
			{
				$memberData = Hash::get($memberData, 'Member.member_id');
			}

			return $memberData;
		}

		//! Get the username for a member, may hit the database.
		/*!
			@param mixed $memberData If array, assumed to be an array of member info in the same format that is returned from database queries, otherwise assumed to be a member id.
			@retval int The username for the member, or 0 if username could not be found.
		*/
		public function getUsernameForMember($memberData)
		{
			if(!isset($memberData))
			{
				return 0;
			}

			if(is_array($memberData))
			{
				$status = Hash::get($memberData, 'Member.username');
				if(isset($status))
				{
					return $status;
				}
				else
				{
					$memberData = Hash::get($memberData, 'Member.member_id');
				}
			}

			$memberInfo = $this->find('first', array('fields' => array('Member.username'), 'conditions' => array('Member.member_id' => $memberData) ));
			if(is_array($memberInfo))
			{
				return Hash::get($memberInfo, 'Member.username');
			}

			return 0;
		}

		//! Get the Status for a member, may hit the database.
		/*!
			@param mixed $memberData If array, assumed to be an array of member info in the same format that is returned from database queries, otherwise assumed to be a member id.
			@retval int The status for the member, or 0 if status could not be found.
		*/
		public function getStatusForMember($memberData)
		{
			if(!isset($memberData))
			{
				return 0;
			}

			if(is_array($memberData))
			{
				$status = Hash::get($memberData, 'Member.member_status');
				if(isset($status))
				{
					return $status;
				}
				else
				{
					$memberData = Hash::get($memberData, 'Member.member_id');
				}
			}

			$memberInfo = $this->find('first', array('fields' => array('Member.member_status'), 'conditions' => array('Member.member_id' => $memberData) ));
			if(is_array($memberInfo))
			{
				$status = Hash::get($memberInfo, 'Member.member_status');
				if(isset($status))
				{
					return (int)$status;
				}
			}

			return 0;
		}

		//! Get the email for a member, may hit the database.
		/*!
			@param mixed $memberData If array, assumed to be an array of member info in the same format that is returned from database queries, otherwise assumed to be a member id.
			@retval int The email for the member, or null if email could not be found.
		*/
		public function getEmailForMember($memberData)
		{
			if(!isset($memberData))
			{
				return null;
			}

			if(is_array($memberData))
			{
				$email = Hash::get($memberData, 'Member.email');
				if(isset($email))
				{
					return $email;
				}
				else
				{
					$memberData = Hash::get($memberData, 'Member.member_id');
				}
			}

			$memberInfo = $this->find('first', array('fields' => array('Member.email'), 'conditions' => array('Member.member_id' => $memberData) ));
			if(is_array($memberInfo))
			{
				$email = Hash::get($memberInfo, 'Member.email');
				if(isset($email))
				{
					return $email;
				}
			}

			return null;
		}

		//! Get a list of e-mail addresses for all members in a Group.
		/*!
			@param int $groupId The id of the group the members must belong to.
			@retval array A list of member e-mails.
		*/
		public function getEmailsForMembersInGroup($groupId)
		{
			$memberIds = $this->GroupsMember->getMemberIdsForGroup($groupId);
			if(count($memberIds) > 0)
			{
				$emails = $this->find('all', array('fields' => array('email'), 'conditions' => array('Member.member_id' => $memberIds)));
				return Hash::extract( $emails, '{n}.Member.email' );
			}
			return array();
		}

		//! Attempt to register a new member record.
		/*!
			@param array $data Information to use to create the new member record.
			@retval mixed Array of details if the member record was created or didn't need to be, or null if member record could not be created.
		*/
		public function registerMember($data)
		{
			if(!isset($data) || !is_array($data))
			{
				return null;
			}

			if( (isset($data['Member']) && isset($data['Member']['email'])) == false )
			{
				return null;
			}

			$this->set($data);

			// Need to validate only the e-mail
			if( !$this->validates( array( 'fieldList' => array( 'email' ) ) ) )
			{
				// Failed
				return null;
			}

			// Grab the e-mail
			$email = $data['Member']['email'];

			// Start to build the result data
			$resultDetails = array();
			$resultDetails['email'] = $email;

			// Do we already know about this e-mail?
			$memberInfo = $this->findByEmail( $email );

			// Find only returns an array if it was successful
			$newMember = !is_array($memberInfo);
			$resultDetails['createdRecord'] = $newMember;

			$memberId = -1;
			if($newMember)
			{
				$memberInfo = $this->createNewMemberInfo( $email );

				if( !$this->_saveMemberData( $memberInfo, array( 'Member' => array('member_id', 'email', 'member_status' )), 0) )
				{
					// Save failed for reasons.
					return null;
				}

				$memberId = $this->id;
			}
			else
			{
				$memberId = Hash::get($memberInfo, 'Member.member_id');
			}
			
			$resultDetails['status'] = (int)$this->getStatusForMember( $memberInfo );
			$resultDetails['memberId'] = $memberId;

			// Success!
			return $resultDetails;
		}

		//! Attempt to set-up login details for a member.
		/*!
			@param int $memberId The id of the member to set-up the login details for.
			@param array $data The data to use.
			@retval bool True on success, otherwise false.
		*/
		public function setupLogin($memberId, $data)
		{
			if(!isset($memberId) || $memberId <= 0)
			{
				return false;
			}

			$memberStatus = $this->getStatusForMember( $memberId );
			if($memberStatus == 0)
			{
				return false;
			}

			if($memberStatus != Status::PROSPECTIVE_MEMBER )
			{
				throw new InvalidStatusException( 'Member does not have status: ' . Status::PROSPECTIVE_MEMBER );
			}

			if(!isset($data) || !is_array($data))
			{
				return false;
			}

			if( ( isset($data['Member']) && 
				  isset($data['Member']['name']) &&
				  isset($data['Member']['username']) &&
				  isset($data['Member']['email']) &&
				  isset($data['Member']['password']) ) == false )
			{
				return false;
			}

			$memberInfo = $this->find('first', array('conditions' => array('Member.member_id' => $memberId)));
			if(!$memberInfo)
			{
				return false;
			}

			// Merge all the data, set the handle to be the same as the username for now
			$hardcodedData = array(
				'handle' => $data['Member']['username'],
				'member_status' => Status::PRE_MEMBER_1,
			);
			unset($data['Member']['member_id']);
			$dataToSave = array('Member' => Hash::merge($memberInfo['Member'], $data['Member'], $hardcodedData));

			$this->set($dataToSave);

			$this->addEmailMustMatch();

			$saveOk = false;
			if($this->validates(array( 'fieldList' => array('name', 'username', 'handle', 'email', 'password', 'password_confirm', 'member_status'))))
			{
				$saveOk = $this->_saveMemberData($dataToSave, array('Member' => array('name', 'username', 'handle', 'member_status')), $memberId);
			}

			$this->removeEmailMustMatch();

			return $saveOk;
		}

		//! Attempt to set-up contact details for a member.
		/*!
			@param int $memberId The id of the member to set-up the contact details for.
			@param array $data The data to use.
			@retval bool True on success, otherwise false.
		*/
		public function setupDetails($memberId, $data)
		{
			if(!isset($memberId) || $memberId <= 0)
			{
				return false;
			}

			$memberStatus = $this->getStatusForMember( $memberId );
			if($memberStatus == 0)
			{
				return false;
			}

			if($memberStatus != Status::PRE_MEMBER_1 )
			{
				throw new InvalidStatusException( 'Member does not have status: ' . Status::PRE_MEMBER_1 );
			}

			if(!isset($data) || !is_array($data))
			{
				return false;
			}

			if( ( isset($data['Member']) && 
				  isset($data['Member']['address_1']) &&
				  isset($data['Member']['address_city']) &&
				  isset($data['Member']['address_postcode']) &&
				  isset($data['Member']['contact_number']) ) == false )
			{
				return false;
			}

			$memberInfo = $this->find('first', array('conditions' => array('Member.member_id' => $memberId)));
			if(!$memberInfo)
			{
				return false;
			}

			$hardcodedData = array(
				'member_status' => Status::PRE_MEMBER_2,
			);
			unset($data['Member']['member_id']);
			$dataToSave = array('Member' => Hash::merge($memberInfo['Member'], $data['Member'], $hardcodedData));

			$this->set($dataToSave);

			if(	$this->validates(array( 'fieldList' => array('address_1', 'address_2', 'address_city', 'address_postcode', 'contact_number', 'member_status'))) )
			{
				return $this->_saveMemberData($dataToSave, array('Member' => array('address_1', 'address_2', 'address_city', 'address_postcode', 'contact_number', 'member_status')), $memberId);
			}

			return false;
		}

		//! Mark a members contact details as invalid.
		/*
			@param int $memberId The id of the member.
			@param array $data The data to use.
			@param int $adminId The id of the member admin who is rejecting the details.
			@retval bool True if the members data was altered successfully, false otherwise.
		*/
		public function rejectDetails($memberId, $data, $adminId)
		{
			// Need some extra validation
	    	$memberEmail = ClassRegistry::init('MemberEmail');

	    	if(!isset($memberId) || $memberId <= 0)
			{
				return false;
			}

			if(!isset($adminId) || $adminId <= 0)
			{
				return false;
			}

			$memberStatus = $this->getStatusForMember( $memberId );
			if($memberStatus == 0)
			{
				return false;
			}

			if($memberStatus != Status::PRE_MEMBER_2 )
			{
				throw new InvalidStatusException( 'Member does not have status: ' . Status::PRE_MEMBER_2 );
			}

			if(!isset($data) || !is_array($data))
			{
				return false;
			}

			if( ( isset($data['MemberEmail']) && 
				  isset($data['MemberEmail']['subject']) &&
				  isset($data['MemberEmail']['message']) ) == false )
			{
				return false;
			}

			$memberInfo = $this->find('first', array('conditions' => array('Member.member_id' => $memberId)));
			if(!$memberInfo)
			{
				return false;
			}

			$hardcodedData = array(
				'member_status' => Status::PRE_MEMBER_1,
			);
			$dataToSave = array('Member' => Hash::merge($memberInfo['Member'], $hardcodedData));

			$this->set($dataToSave);

			if( $memberEmail->validates( array( 'fieldList' => array( 'subject', 'body' ) ) ) )
			{
				return $this->_saveMemberData($dataToSave, array('Member' => array('member_status')), $adminId);
			}
		}

		//! Mark a members details as valid.
		/*!
			@param int $memberId The id of the member who's details we want to mark as valid.
			@param array $data The account data to use.
			@param int $adminId The id of the member admin who's accepting the details.
			@retval mixed Array of member details on success, or null on failure.
		*/
		public function acceptDetails($memberId, $data, $adminId)
		{
			if(!isset($memberId) || $memberId <= 0)
			{
				return null;
			}

			if(!isset($adminId) || $adminId <= 0)
			{
				return false;
			}

			$memberStatus = $this->getStatusForMember( $memberId );
			if($memberStatus == 0)
			{
				return null;
			}

			if($memberStatus != Status::PRE_MEMBER_2 )
			{
				throw new InvalidStatusException( 'Member does not have status: ' . Status::PRE_MEMBER_2 );
			}

			if(!isset($data) || !is_array($data))
			{
				return null;
			}

			$memberInfo = $this->find('first', array('conditions' => array('Member.member_id' => $memberId)));
			if(!$memberInfo)
			{
				return null;
			}

			$hardcodedData = array(
				'member_status' => Status::PRE_MEMBER_3,
			);
			unset($data['Member']);
			$dataToSave = array('Member' => Hash::merge($memberInfo['Member'], $hardcodedData));

			$dataToSave = Hash::merge($dataToSave, $data);

			$this->set($dataToSave);

			if( $this->_saveMemberData($dataToSave, array('Member' => array( 'member_status', 'account_id' )), $adminId) )
			{
				return $this->getSoDetails($memberId);
			}

			return null;
		}

		//! Approve a member, making them a current member.
		/*
			@param int $memberId The id of the member to approve.
			@param int $adminId The id of the member admin who is approving the member.
			@retval mixed Array of member details on success, or null on failure.
		*/
		public function approveMember($memberId, $adminId)
		{
			if(!isset($memberId) || $memberId <= 0)
			{
				return null;
			}

			$memberStatus = $this->getStatusForMember( $memberId );
			if($memberStatus == 0)
			{
				return null;
			}

			if($memberStatus != Status::PRE_MEMBER_3 )
			{
				throw new InvalidStatusException( 'Member does not have status: ' . Status::PRE_MEMBER_3 );
			}

			$memberInfo = $this->find('first', array('conditions' => array('Member.member_id' => $memberId)));
			if(!$memberInfo)
			{
				return null;
			}

			$hardcodedMemberData = array(
				'member_status' => Status::CURRENT_MEMBER,
				'unlock_text' => 'Welcome ' . $memberInfo['Member']['name'],
				'credit_limit' => 5000,
				'join_date' => date( 'Y-m-d' ),
			);
			$dataToSave = array('Member' => Hash::merge($memberInfo['Member'], $hardcodedMemberData));
			$dataToSave['Pin'] = array(
				'unlock_text' => 'Welcome',
				'pin' => $this->Pin->generateUniquePin(),
				'state' => 40,
				'member_id' => $memberId,
			);

			$this->set($dataToSave);

			$fieldsToSave = array(
				'Member' => array( 
					'member_status', 
					'unlock_text', 
					'credit_limit', 
					'join_date' 
				), 
				'Pin' => array(
					'unlock_text', 
					'pin', 
					'state', 
					'member_id'
				)
			);

			if( $this->_saveMemberData($dataToSave, $fieldsToSave, $adminId) )
			{
				return $this->getApproveDetails($memberId);
			}

			return null;
		}

		//! Change a users password.
		/*
			@param int $memberId The id of the member whose password is being changed.
			@param int $adminId The id of the member who is changing the password.
			@param array $data The array of password data.
		*/
		public function changePassword($memberId, $adminId, $data)
		{
			// Need some extra validation
	    	$changePasswordModel = ClassRegistry::init('ChangePassword');

	    	if(!isset($memberId) || $memberId <= 0)
			{
				return false;
			}

			if(!isset($adminId) || $adminId <= 0)
			{
				return false;
			}

			$memberStatus = $this->getStatusForMember( $memberId );
			if($memberStatus == 0)
			{
				return false;
			}

			if($memberStatus == Status::PROSPECTIVE_MEMBER )
			{
				throw new InvalidStatusException( 'Member has status: ' . Status::PROSPECTIVE_MEMBER );
			}

			if(	$memberId != $adminId && 
				!($this->GroupsMember->isMemberInGroup($adminId, Group::MEMBER_ADMIN) || $this->GroupsMember->isMemberInGroup($adminId, Group::FULL_ACCESS)))
			{
				throw new NotAuthorizedException('Only member admins can change another members password.');
			}

			if(!isset($data) || !is_array($data))
			{
				return false;
			}

			if( ( isset($data['ChangePassword']) && 
				  isset($data['ChangePassword']['current_password']) &&
				  isset($data['ChangePassword']['new_password']) &&
				  isset($data['ChangePassword']['new_password_confirm']) ) == false )
			{
				return false;
			}

			$changePasswordModel->set($data);
			if(!$changePasswordModel->validates())
			{
				return false;
			}

			$passwordToCheckMember = $this->find('first', array('conditions' => array('Member.member_id' => $adminId)));
			if(!$passwordToCheckMember)
			{
				return false;
			}

			$passwordToSetMember = ($adminId === $memberId) ? $passwordToCheckMember : $this->find('first', array('conditions' => array('Member.member_id' => $memberId)));

			if(!$passwordToSetMember)
			{
				return false;
			}

			if($this->krbCheckPassword(Hash::get($passwordToCheckMember, 'Member.username'), Hash::get($data, 'ChangePassword.current_password')))
			{
				return $this->krbChangePassword(Hash::get($passwordToSetMember, 'Member.username'),Hash::get($data, 'ChangePassword.new_password'));
			}

			return false;
		}

		//! Generate a forgot password request from an e-mail.
		/*
			@param array $data Array of data containing the user submitted e-mail.
			@retval mixed An array of id and email data if creation succeeded, false otherwise.
		*/
		public function createForgotPassword($data)
		{
			// Need some extra validation
	    	$forgotPasswordModel = ClassRegistry::init('ForgotPassword');

	    	if(!isset($data) || !is_array($data))
			{
				return false;
			}

			if( ( isset($data['ForgotPassword']) && 
				  isset($data['ForgotPassword']['email']) ) == false )
			{
				return false;
			}

			if ( isset($data['ForgotPassword']['new_password']) ||
			  	isset($data['ForgotPassword']['new_password_confirm']) )
			{
				return false;
			}

			$emailAddress = Hash::get($data, 'ForgotPassword.email');

			$memberInfo = $this->find('first', array('conditions' => array('Member.email' => $emailAddress), 'fields' => array('Member.member_id', 'Member.member_status')));
			if($memberInfo)
			{
				$memberStatus = $this->getStatusForMember( $memberInfo );
				if($memberStatus == 0)
				{
					return false;
				}

				if($memberStatus == Status::PROSPECTIVE_MEMBER )
				{
					throw new InvalidStatusException( 'Member has status: ' . Status::PROSPECTIVE_MEMBER );
				}

				$guid = $forgotPasswordModel->createNewEntry(Hash::get($memberInfo, 'Member.member_id'));
				if($guid != null)
				{
					return array('id' => $guid, 'email' => $emailAddress);
				}
			}

			return false;
		}

		//! Complete a forgot password request
		/*
			@param string $guid The id of the forgot password request.
			@param array $data Array of data containing the user submitted e-mail.
			@retval bool True if password was changed, false otherwise.
		*/
		public function completeForgotPassword($guid, $data)
		{
			if(!ForgotPassword::isValidGuid($guid))
			{
				return false;
			}

			// Need some extra validation
	    	$forgotPasswordModel = ClassRegistry::init('ForgotPassword');

	    	if(!isset($data) || !is_array($data))
			{
				return false;
			}

			if( ( isset($data['ForgotPassword']) && 
				  isset($data['ForgotPassword']['email']) &&
				  isset($data['ForgotPassword']['new_password']) &&
				  isset($data['ForgotPassword']['new_password_confirm'])  ) == false )
			{
				return false;
			}

			$forgotPasswordModel->set($data);
			if($forgotPasswordModel->validates())
			{
				$emailAddress = Hash::get($data, 'ForgotPassword.email');

				$memberInfo = $this->find('first', array('conditions' => array('Member.email' => $emailAddress), 'fields' => array('Member.member_id')));
				if($memberInfo)
				{
					$memberId = $this->getIdForMember($memberInfo);
					if(	$memberId > 0 && 
						$forgotPasswordModel->isEntryValid($guid, $memberId))
					{
						$username = $this->getUsernameForMember($memberId);
						if($username)
						{
							$password = Hash::get($data, 'ForgotPassword.new_password');

							$dataSource = $this->getDataSource();
							$dataSource->begin();

							if( ($this->setPassword($username, $password, true) &&
								$forgotPasswordModel->expireEntry($guid)) )
							{
								$dataSource->commit();
								return true;
							}
							
							$dataSource->rollback();
							return false;
						}
					}
				}				
			}
			return false;
		}

		//! Get a members name, email and payment ref.
		/*
			@param int $memberId The id of the member to get the details for.
			@retval mixed Array of info on success, null on failure.
		*/
		public function getSoDetails($memberId)
		{
			$memberInfo = $this->find('first', array('conditions' => array('Member.member_id' => $memberId)));
			if($memberInfo)
			{
				$name = Hash::get($memberInfo, 'Member.name');
				$email = Hash::get($memberInfo, 'Member.email');
				$paymentRef = Hash::get($memberInfo, 'Account.payment_ref');

				if(isset($name) && isset($email) && isset($paymentRef))
				{
					return array(
						'name' => $name,
						'email' => $email,
						'paymentRef' => $paymentRef,
					);
				}
			}
			return null;
		}

		//! Get a members name, email and pin.
		/*
			@param int $memberId The id of the member to get the details for.
			@retval mixed Array of info on success, null on failure.
		*/
		public function getApproveDetails($memberId)
		{
			$memberInfo = $this->find('first', array('conditions' => array('Member.member_id' => $memberId)));
			if($memberInfo)
			{
				$name = Hash::get($memberInfo, 'Member.name');
				$email = Hash::get($memberInfo, 'Member.email');
				$pin = Hash::get($memberInfo, 'Pin.pin');

				if(isset($name) && isset($email) && isset($pin))
				{
					return array(
						'name' => $name,
						'email' => $email,
						'pin' => $pin,
					);
				}
			}
			return null;
		}

		//! Get a list of account and member details that is suitable for populating a drop-down box
		/*!
			@retval null List of values on success, null on failure.
		*/
		public function getReadableAccountList()
		{
			$memberList = $this->find('list', array(
				'fields' => array('member_id', 'name', 'account_id'), 
				'conditions' => array('Member.account_id !=' => null))
			);

			$accountList = array();
			foreach ($memberList as $accountId => $members) 
			{
				$memberNames = array();
				foreach ($members as $id => $name) 
				{
					array_push($memberNames, $name);
				}
				
				$accountList[$accountId] = String::toList($memberNames);
			}
			$accountList['-1'] = 'Create new';
			ksort($accountList);

			return $accountList;
		}

		//! Validate that e-mail data is ok.
		/*!
			@param array $data The data to validate.
			@retval mixed Array of e-mail data if $data is valid, false otherwise.
		*/
		public function validateEmail($data)
		{
			if(	is_array($data) &&
				isset($data['MemberEmail']) &&
				isset($data['MemberEmail']['subject']) &&
				isset($data['MemberEmail']['message']) )
			{
				$emailModel = ClassRegistry::init('MemberEmail');
				$emailModel->set($data);
				if($emailModel->validates($data))
				{
					return array('subject' => Hash::get($data, 'MemberEmail.subject'), 'message' => Hash::get($data, 'MemberEmail.message'));
				}
			}
			return false;
		}

		//! Create or save a member record, and all associated data.
		/*! 
			@param array $memberInfo The information to use to create or update the member record.
			@param array $fields The fields that should be saved.
			@param int $adminId The id of the member who is making the change that needs saving.
			@retval bool True if save succeeded, false otherwise.
		*/
		private function _saveMemberData($memberInfo, $fields, $adminId)
		{
			$dataSource = $this->getDataSource();
			$dataSource->begin();

			$memberId = Hash::get( $memberInfo, 'Member.member_id' );

			// If the member already exists, sort out the groups
			$oldStatus = 0;
			$newStatus = (int)$this->getStatusForMember( $memberInfo );
			if($memberId != null)
			{
				$oldStatus = (int)$this->getStatusForMember( $memberId );

				if($newStatus != $oldStatus)
				{
					// We shall need to save the Group
					if(!in_array('Group', $fields))
					{
						array_push($fields, 'Group');
					}

					$newGroups = array( 'Group' );
					if( $newStatus != Status::CURRENT_MEMBER )
					{
						$newGroups['Group'] = array();
					}
					else
					{
						// Get a list of existing groups this member is a part of
						$existingGroups = $this->GroupsMember->getGroupIdsForMember( $memberId );
						if(!in_array(Group::CURRENT_MEMBERS, $existingGroups))
						{
							array_push($existingGroups, Group::CURRENT_MEMBERS);
						}

						$groupIdx = 0;
						foreach ($existingGroups as $group) 
						{
							$newGroups['Group'][$groupIdx] = $group;
							$groupIdx++;
						}
					}

					$memberInfo['Group'] = $newGroups;
				}

				// Do we have to change the password?
				if(	isset($memberInfo['Member']['username']) && 
					isset($memberInfo['Member']['password']) )
				{
					$username = Hash::get($memberInfo, 'Member.username');
					$password = Hash::get($memberInfo, 'Member.password');

			    	if(!$this->setPassword($username, $password, true))
			    	{
			    		$dataSource->rollback();
			    		return false;
			    	}
				}
			}
			else
			{
				$this->Create();
			}

			// Do we want to be saving an account id?
			if(	isset($fields['Member']) && 
				is_array($fields['Member']) && 
				in_array('account_id', $fields['Member']))
			{
				// Attempt to get the account id we're meant to be saving, try from the account field first.
				$accountId = Hash::get($memberInfo, 'Account.account_id');

				if(!isset($accountId))
				{
					// Try the member field?
					$accountId = Hash::get($memberInfo, 'Member.account_id');
				}

				// Do we actually have an account id?
				if(isset($accountId))
				{
					// Check with the Account model if this account exists or not
					// should return the id of the account we should be saving
					$accountId = $this->Account->setupAccountIfNeeded($accountId);
					if($accountId < 0)
					{
						// Either account creation failed or account does not exist.
						$dataSource->rollback();
						return false;
					}

					// Set the account id in the member info, as it may have changed.
					$memberInfo = Hash::insert($memberInfo, 'Member.account_id', $accountId);
				}
			}

			unset($memberInfo['Account']);

			if( !$this->saveAll( $memberInfo, array( 'fieldList' => $fields )) )
			{
				$dataSource->rollback();
				return false;
			}

			// Do we need to create a status update record?
			if($newStatus != $oldStatus)
			{
				// If $memberId is null then we've just created this member.
				if($memberId == null)
				{
					$memberId = $this->id;
				}

				// Admin id of 0 means the member is making the change themselves
				if($adminId === 0)
				{
					$adminId = $memberId;
				}

				if(!$this->StatusUpdate->createNewRecord( $memberId, $adminId, $oldStatus, $newStatus ))
				{
					$dataSource->rollback();
					return false;
				}
			}

			// We're good
			$dataSource->commit();
			return true;
		}

		//! Set the password for the member, with the option to create a new password entry if needed.
		/*!
			@param string $username The username of the member.
			@param string $password The new password.
			@param bool $allowCreate If true, will create a new auth record for a member that doesn't currently have one.
			@retval bool True if the password was set ok, false otherwise.
		*/
		private function setPassword($username, $password, $allowCreate)
		{
			switch ($this->krbUserExists($username)) 
	    	{
	    		case TRUE:
	    			return $this->krbChangePassword($username, $password);

	    		case FALSE:
	    			return ($allowCreate && $this->krbAddUser($username, $password));

	    		default:
	    			return false;
	    	}
	    	return false;
		}


		//! Get a summary of the member records for all members that match the conditions.
		/*!
			@retval array A summary (id, name, email, Status and Groups) of the data of all members that match the conditions.
		*/
		private function _getMemberSummary($paginate, $conditions = array())
		{
			$findOptions = array('conditions' => $conditions);

			if($paginate)
			{
				return $findOptions;
			}

			$info = $this->find( 'all', $findOptions );

			return $this->formatMemberInfo($info);
		}
	}
?>