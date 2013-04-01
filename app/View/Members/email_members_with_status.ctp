<!-- File: /app/View/Member/email_members_with_status.ctp -->

<?php
	$this->Html->addCrumb('Members', '/members');
	$this->Html->addCrumb('Email members with status ' . $status['name'], '/members/email_members_with_status/' . $status['id']);
?>

<?php
	$membersWithEmails = array();
	$membersWithoutEmails = array();
	foreach ($members as $memberInfo) 
	{
		if( isset($memberInfo['email']) &&
			$memberInfo['email'] != null &&
			strlen(trim($memberInfo['email'])) > 0)
		{
			array_push($membersWithEmails, $memberInfo);
		}
		else
		{
			array_push($membersWithoutEmails, $memberInfo);	
		}
	}
?>

<?php if(count($members) > 0 && count($membersWithEmails) > 0): ?>

	<?php
		echo $this->Form->create('MemberEmail');
		echo $this->Form->input('subject');
		echo $this->Tinymce->input('Member.message', 
			array( 'label' => 'Message' ),
			array( 'language'=>'en' ), 
            'basic' 
        ); 
		#echo $this->Form->input('message', array('type' => 'textarea'));
	?>

	<p>This e-mail will be sent to the following members:</p>
	<?php
		echo '<ul>';
		foreach ($membersWithEmails as $memberInfo) {
			echo '<li>';

			echo $memberInfo['name'] . ' [' . $memberInfo['email'] . ']';

			echo '</li>';
		}
		echo '</ul>';

		if(count($membersWithoutEmails) > 0)
		{
			echo '</br><p>However, the following members do not have e-mail addresses so they will not be contacted:</p>';
			echo '<ul>';
			foreach ($membersWithoutEmails as $memberInfo) {
				echo '<li><strong>' . $memberInfo['name'] . '</strong></li>';
			}
			echo '</ul>';
		}
		echo $this->Form->end('Send');
	?>

<?php else: ?>
	<p>No members to e-mail.</p>
<?php endif; ?>
