<?php

App::uses('Component', 'Controller');

# AT [16/09/2012] NavComponent exists to check if a navigation link is
# allowed (by checking the authorization), allowed links are added to a list
# which can then be rendered in the view
class NavComponent extends Component {
	
	public $components = array( 'Auth' );

	var $allowedActions = array();

	# Add a navigation option, testing if it's authorized first
	public function add($text, $controller, $action, $params = array())
	{
		#print_r($this->Auth);
		$url = '/' . $controller . '/' . $action;

		if(count($params) > 0)
		{
			$url .= '/' . join($params, '/');
		}

		$request = new CakeRequest($url, false);
		$request->addParams(array(
			'plugin' => null,
			'controller' => $controller,
			'action' => $action,
			'pass' => $params,
		));
		if( $this->Auth->isAuthorized(AuthComponent::user(), $request) )
		{
			array_push($this->allowedActions, array( 'text' => $text, 'controller' => $controller, 'action' => $action, 'params' => $params ) );
		}
	}

	public function get_allowed_actions()
	{
		return $this->allowedActions;
	}

}

?>