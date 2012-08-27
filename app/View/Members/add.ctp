<!-- File: /app/View/Member/add.ctp -->

<?php
    $this->Html->addCrumb('Members', '/members');
	$this->Html->addCrumb('Add Member', '/members/add');
?>

<h1>Add Member</h1>
<?php
	echo $this->Form->create('Member');
	echo $this->Form->input('name');
	echo $this->Form->input('email');
	echo $this->Form->input('handle');

	# Pin details
	echo $this->Form->hidden('Pin.pin');

	echo $this->Form->end('Add Member');
?>