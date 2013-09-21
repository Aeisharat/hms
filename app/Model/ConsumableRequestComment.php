<?php

	App::uses('AppModel', 'Model');

	/**
	 * Model for all consumable request comment data
	 *
	 *
	 * @package       app.Model
	 */
	class ConsumableRequestComment extends AppModel 
	{
		public $useTable = 'consumable_request_comments';	//!< Specify the table to use.
		public $primaryKey = 'request_comment_id';			//!< Specify the promary key to use.

		public $belongsTo = array(
			'Member' => array(
				'className' => 'Member',
            	'foreignKey' => 'member_id',
            	'fields' => array( 'member_id', 'username' ),
			),
		);

		public $validate = array(
	        'text' => array(
	            'notEmpty' => array(
	            	'rule' => 'notEmpty',
	            	'message' => 'Comment must have somne text',
	            	'required' => true,
	            	'allowEmpty' => false,
	            )
	        ),
	        'request_id' => array(
	            'number' => array(
	            	'rule' => array('naturalNumber', false),
	        		'message' => 'Comment must have a belong to a request',
	            	'required' => true,
	            	'allowEmpty' => false,
	            )
	        ),
	        'member_id' => array(
	        	'number' => array(
	        		'rule' => array('naturalNumber', false),
	        		'message' => 'Member must be valid',
	        		'required' => false,
	        		'allowEmpty' => true,
	        	),
	        ),
	    );

		//! Add a new comment
		/*
			@param array $data The date for the comment.
			@param mixed $memberId The id of the member making the comment, may be null.
			@retval bool True if comment was added, false otherwise.
		*/
		public function add($data, $memberId)
		{
			// Set the memberId
			if(	is_array($data) && 
				array_key_exists('ConsumableRequestComment', $data) &&
				is_array($data['ConsumableRequestComment']) )
			{
				$data['ConsumableRequestComment']['member_id'] = $memberId;
			}
			
			$this->create($data);
			if(!$this->validates())
			{
				throw new InvalidArgumentException('Information in $data did not correspond with validation rules');
			}

			return (bool)$this->save($data);
		}

		//! Get all comments for a request
		/*
			@param int $requestId The id of the request to retrieve all the comments for.
			@retval array An array of comment data
		*/
		public function getAllForRequest($requestId)
		{
			if(!is_numeric($requestId) || 
				$requestId <= 0)
			{
				throw new InvalidArgumentException('$requestId must be numeric and greater than zero');
			}

			$record = $this->findByCommentRequestId($requestId);
			if(!is_array($record) || count($record) == 0)
			{
				return array();
			}

			return $this->_formatRecord($record);
		}

		//! Format a record for use outside of this class
		/*!
			@param array $record The data to be formatted.
			@retval array The formatted data.
		*/
		private function _formatRecord($record)
		{
			$formattedData = $record['ConsumableRequestComment'];
			$formattedData['member'] = $record['Member'];
			return $formattedData;
		}
	}
?>