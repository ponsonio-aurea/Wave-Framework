<?php

/**
 * Wave Framework <http://www.waveframework.com>
 * URL Controller
 *
 * Wave Framework comes with a URL Controller and a sitemap system that is used to build a 
 * website on Wave Framework. This URL controller is entirely optional and can be removed 
 * from a system if you plan to implement your own URL Controller or simply use Wave 
 * Framework for API, without a website.
 *
 * @package    Tools
 * @author     Kristo Vaher <kristo@waher.net>
 * @copyright  Copyright (c) 2012, Kristo Vaher
 * @license    GNU Lesser General Public License Version 3
 * @tutorial   /doc/pages/guide_url.htm
 * @since      1.0.0
 * @version    3.2.3
 */

class WWW_controller_url extends WWW_Factory {

	/**
	 * This method is called by Data Handler to find the View that is being requested based 
	 * on the URL that (usually) comes from the user agent request URL.
	 *
	 * @param array $input input data sent to controller
	 * @input [url] This is URL to be solved
	 * @return array through returnViewData method
	 * @output [controller] controller to use for View
	 * @output [controller-method] what controller method to use
	 * @output [view-method] what View method to use
	 * @output [subview] subview variable
	 * @output [hidden] if the page should be hidden from menu lists
	 * @output mixed might include many other View-specific variables
	 */
	public function solve($input){
		
		// Default view is loaded from State (this is loaded when no URL is defined)
		$view404=$this->getState('404-view');
		
		// Custom request URL can be used, this is required
		if(isset($input['url'])){
			// Request string is loaded from input
			$request=$input['url'];
		} else {
			// Formatting and returning the expected result array
			return $this->returnViewData(array('view'=>$view404,'header'=>'HTTP/1.1 404 Not Found'));
		}
		
		// Web root is the base directory of the website
		$webRoot=$this->getState('web-root');
		
		// This setting will force that even the first language (first in languages array) has to be represented in URL
		$enforceSlash=$this->getState('enforce-url-end-slash');
		// This setting means that URL has to end with a slash
		$enforceLanguageUrl=$this->getState('enforce-first-language-url');
		
		// Default language is loaded from State
		$language=$this->getState('language');
		// List of defined languages is loaded from State
		$languages=$this->getState('languages');
		
		// Default view is loaded from State (this is loaded when no URL is defined)
		$viewHome=$this->getState('home-view');
		// By default it is assumed that home view is used
		$view=$viewHome;
		
		// To solve the request GET is separated from URL nodes
		$requestNodesRaw=explode('?',$request,2);
		// This array stores all the URL nodes that will be matched against sitemap
		$urlNodes=array();
		
		// This is used for testing if the returned URL should be home or not
		$returnHome=false;
		
		// If there is no request URL set
		if($requestNodesRaw[0]=='' || $requestNodesRaw[0]=='/'){
		
			// If first language code has to be defined in URL, system redirects to URL that has it, otherwise returns home view data
			if($enforceLanguageUrl==true){
				// User agent is redirected to URL that has just the language node set
				if(isset($requestNodesRaw[1])){
					return array('www-permanent-redirect'=>$webRoot.$language.'/?'.$requestNodesRaw[1]);
				} else {
					return array('www-permanent-redirect'=>$webRoot.$language.'/');
				}
			} else {
				// Expecting to return Home view
				$returnHome=true;
			}
			
		} else {
		
			// Request is formatted to remove all potentially harmful characters
			$requestFormatted=preg_replace('/^[\/]|[^A-Za-z\-\_0-9\/]/i','',strtolower($requestNodesRaw[0]));
			// Request is exploded into an array that will be looped to find proper view
			$requestNodes=explode('/',$requestFormatted);
			// If slash is enforced at the end of the URL then user agent is redirected to such an URL
			if($enforceSlash==true && end($requestNodes)!=''){
				// If GET variables were set, system redirects to proper URL that has a slash in the end and appends the GET variables
				if(isset($requestNodesRaw[1])){
					return array('www-permanent-redirect'=>$webRoot.$requestNodesRaw[0].'/?'.$requestNodesRaw[1]);
				} else {
					return array('www-permanent-redirect'=>$webRoot.$requestNodesRaw[0].'/');
				}
			}
			
			// Looping through all the URL nodes from the request
			foreach($requestNodes as $nodeKey=>$node){
			
				// As long as the URL node in the request is not empty, it is taken into account for finding the proper view
				if($node!='' || !isset($requestNodes[$nodeKey+1])){
					
					// If this is the first request node and it is found in languages array
					if($nodeKey==0 && in_array($node,$languages)){
					
						// Language was found and is defined as the request language
						$language=$node;
						// If this is the first language and language node is not required in URL, user agent is redirected to a URL without it
						if($enforceLanguageUrl==false && $language==$languages[0]){
							// We unset the first node, as it was not required
							unset($requestNodes[$nodeKey]);
							// If GET variables were set, system redirects to URL without the language and appends the GET variables
							if(isset($requestNodesRaw[1])){ 
								return array('www-permanent-redirect'=>$webRoot.implode('/',$requestNodes).'?'.$requestNodesRaw[1]);
							} else {
								return array('www-permanent-redirect'=>$webRoot.implode('/',$requestNodes));
							}
						}
						
					} else {
					
						// If language node is required in URL and the first request node was not a language, it is added and user agent is redirected
						if($nodeKey==0 && $enforceLanguageUrl==true){
							// User agent is redirected to the same URL as before, but with the default language node added
							if(isset($requestNodesRaw[1])){
								return array('www-permanent-redirect'=>$webRoot.$language.'/'.$requestFormatted.'?'.$requestNodesRaw[1]);
							} else {
								return array('www-permanent-redirect'=>$webRoot.$language.'/'.$requestFormatted);
							}
						} else {
							// If language node is set in request, but has no second URL node set, it is also assumed that it is default view
							if($requestNodes[0]==$language && $nodeKey==1 && (!isset($requestNodes[1]) || $requestNodes[1]=='')){
								// Expecting to return Home view
								$returnHome=true;
							} else {
								// Every other request node is added to the array that looks for matching URL from URL Map
								if($node!=''){
									$urlNodes[]=$node;
								}
							}
						}
						
					}
					
				} else {
					// Formatting and returning the expected result array
					return $this->returnViewData(array('view'=>$view404,'language'=>$language,'header'=>'HTTP/1.1 404 Not Found'));
				}

			}
			
		}
		
		// All nodes of URL's that were not found as modules are stored here
		$dynamicUrl=array();
		
		// Number of URL nodes
		$urlNodeCount=count($urlNodes);
		
		// URL Map is stored in this array
		$siteMap=$this->getSitemapRaw($language);
		if(!$siteMap){
			// Formatting and returning the expected result array
			return $this->returnViewData(array('view'=>$view404,'language'=>$language,'header'=>'HTTP/1.1 404 Not Found'));
		}
		
		// Exploding sitemap array variables to nodes to match against
		foreach($siteMap as $key=>$settings){
			$siteMap[$key]['nodes']=explode('/',$key);
		}
		
		// Array that stores information from sitemap file
		$siteMapInfo=$siteMap[$viewHome];
		
		// If home is not expected to be returned
		if(!$returnHome){
		
			$matchKey=0;
			// System loops through URL nodes and attempts to find a match in URL Map
			while(!empty($urlNodes) && !empty($siteMap)){
				foreach($siteMap as $key=>$settings){
					// URL length needs to match the URL declaration in Sitemap
					if($matchKey==0 && $urlNodeCount!=count($settings['nodes'])){
						unset($siteMap[$key]);
					} else {
						// If the node is not dynamic
						if($settings['nodes'][$matchKey][0]!=':'){
							if($urlNodes[$matchKey]!=$settings['nodes'][$matchKey]){
								unset($siteMap[$key]);
							}
						} else {
							// Matching the dynamic URL's
							$matched=explode(':',$settings['nodes'][$matchKey],3);
							switch($matched[1]){
								case 'numeric':
									if($matched[2]==''){
										if(!preg_match('/^[0-9]*$/i',$urlNodes[$matchKey])){
											unset($siteMap[$key]);
										} else {
											$dynamicUrl[]=$urlNodes[$matchKey];
										}
									} else {
										// Finding the match parameters
										$parameters=explode('-',$matched[2]);
										if(!preg_match('/^[0-9\-\_]*$/i',$urlNodes[$matchKey]) || ($parameters[0]!='*' && intval($urlNodes[$matchKey])<$parameters[0]) || ($parameters[1]!='*' && intval($urlNodes[$matchKey])>$parameters[1])){
											unset($siteMap[$key]);
										} else {
											$dynamicUrl[]=$urlNodes[$matchKey];
										}
									}
									break;
								case 'alpha':
									if($matched[2]==''){
										if(!preg_match('/^[a-z\-\_]*$/i',$urlNodes[$matchKey])){
											unset($siteMap[$key]);
										} else {
											$dynamicUrl[]=$urlNodes[$matchKey];
										}
									} else {
										// Finding the match parameters
										$parameters=explode('-',$matched[2]);
										if(!preg_match('/^[a-z\-\_]*$/i',$urlNodes[$matchKey]) || ($parameters[0]!='*' && strlen($urlNodes[$matchKey])<$parameters[0]) || ($parameters[1]!='*' && strlen($urlNodes[$matchKey])>$parameters[1])){
											unset($siteMap[$key]);
										} else {
											$dynamicUrl[]=$urlNodes[$matchKey];
										}
									}
									break;
								case 'alphanumeric':
									if($matched[2]==''){
										if(!preg_match('/^[a-z0-9\-\_]*$/i',$urlNodes[$matchKey])){
											unset($siteMap[$key]);
										} else {
											$dynamicUrl[]=$urlNodes[$matchKey];
										}
									} else {
										// Finding the match parameters
										$parameters=explode('-',$matched[2]);
										if(!preg_match('/^[a-z0-9\-\_]*$/i',$urlNodes[$matchKey]) || ($parameters[0]!='*' && strlen($urlNodes[$matchKey])<$parameters[0]) || ($parameters[1]!='*' && strlen($urlNodes[$matchKey])>$parameters[1])){
											unset($siteMap[$key]);
										} else {
											$dynamicUrl[]=$urlNodes[$matchKey];
										}
									}
									break;
								case 'fixed':
									if($matched[2]!=''){
										// Finding the match parameters
										$matches=explode(',',$matched[2]);
										if(!in_array($urlNodes[$matchKey],$matches)){
											unset($siteMap[$key]);
										} else {
											$dynamicUrl[]=$urlNodes[$matchKey];
										}
									} else {
										unset($siteMap[$key]);
									}
									break;
							}
						}
					}
				}
				unset($urlNodes[$matchKey]);
				$matchKey++;
			}
			
			// If all URL nodes have been matched and there's still a URL in the Sitemap array
			if(empty($urlNodes) && !empty($siteMap)){
				$siteMapInfo=array_pop($siteMap);
				$view=$siteMapInfo['view'];
			} else {
				// Formatting and returning the expected result array
				return $this->returnViewData(array('view'=>$view404,'language'=>$language,'header'=>'HTTP/1.1 404 Not Found'));
			}
		
			
			// If the found view is home view, then we simply redirect to home view without the long url
			if(empty($dynamicUrl) && $view==$viewHome){
			
				// If first language is used and it is not needed to use language URL in first language
				if($enforceLanguageUrl==false && $language==$languages[0]){
					// If request nodes are set in the URL
					if(isset($requestNodesRaw[1])){
						return array('www-permanent-redirect'=>$webRoot.'?'.$requestNodesRaw[1]);
					} else {
						return array('www-permanent-redirect'=>$webRoot);
					}
				} else {
					// If request nodes are set in the URL
					if(isset($requestNodesRaw[1])){
						return array('www-permanent-redirect'=>$webRoot.$language.'/?'.$requestNodesRaw[1]);
					} else {
						return array('www-permanent-redirect'=>$webRoot.$language.'/');
					}
				}
			
			}
		
		}
				
		// Populating sitemap info with additional details
		$siteMapInfo['request-url']='/'.$requestNodesRaw[0];
		if(isset($requestNodesRaw[1])){
			$siteMapInfo['request-parameters']=$requestNodesRaw[1];
		} else {
			$siteMapInfo['request-parameters']='';
		}
		$siteMapInfo['language']=$language;
		$siteMapInfo['web-root']=$webRoot;
		
		// Array of unsolved URL nodes is reversed if it is not empty
		if(!empty($dynamicUrl)){
			// Unsolved URL's are reversed so that they can be used in the order they were defined in URL
			$dynamicUrl=array_reverse($dynamicUrl);
		} else {
			// It is possible to assign temporary or permanent redirection in Sitemap, causing 302 or 301 redirect
			if(isset($siteMapInfo['temporary-redirect']) && $siteMapInfo['temporary-redirect']!=''){
				// Query string is also sent, if it has been defined
				if(isset($requestNodesRaw[1]) && strpos($siteMapInfo['temporary-redirect'],'?')===false){
					return array('www-temporary-redirect'=>$siteMapInfo['temporary-redirect'].'?'.$requestNodesRaw[1]);
				} else {
					return array('www-temporary-redirect'=>$siteMapInfo['temporary-redirect']);
				}
			} elseif(isset($siteMapInfo['permanent-redirect']) && $siteMapInfo['permanent-redirect']!=''){
				// Query string is also sent, if it has been defined
				if(isset($requestNodesRaw[1]) && strpos($siteMapInfo['permanent-redirect'],'?')===false){
					return array('www-permanent-redirect'=>$siteMapInfo['permanent-redirect'].'?'.$requestNodesRaw[1]);
				} else {
					return array('www-permanent-redirect'=>$siteMapInfo['permanent-redirect']);
				}
			}
		}
		
		// Populating sitemap info with additional details
		$siteMapInfo['dynamic-url']=$dynamicUrl;
			
		// Formatting and returning the expected result array
		return $this->returnViewData($siteMapInfo);
		
	}
	
	/**
	 * This function formats and returns View data for View Controller
	 *
	 * @param array $data data from Sitemap
	 * @return array
	 */
	private function returnViewData($data){
	
		// VIEW DEFAULTS
			
			$data+=array(
				'controller'=>'view',
				'controller-method'=>'load',
				'view-method'=>'render',
				'subview'=>'',
				'hidden'=>0
			);
	
		// DEFAULTS FOR VIEW DATA
			
			// If dynamic URL is assigned as part of cache tag
			if(isset($data['cache-tag'],$data['cache-tag-dynamic']) && $data['cache-tag-dynamic']==1 && !empty($data['dynamic-url'])){
				$data['cache-tag'].='-'.implode('-',$data['dynamic-url']);
			}
			
			// If project title is not set by Sitemap, system defines the State project title as the value
			if(!isset($data['project-title'])){
				$data['project-title']=$this->getState('project-title');
			}
			
			// Robots data is also returned to views
			if(!isset($data['robots'])){
				$data['robots']=$this->getState('robots');
			}
			
		// HEADERS
		
			// These headers will be set by API
			$data['www-set-header']=array();
			
			if(isset($data['header'])){
				$data['www-set-header'][]=$data['header'];
			}
			
			// Writing robots data to header
			if($data['robots']!=''){
				// This header is not 'official HTTP header', but is widely supported by Google and others
				$data['www-set-header'][]='X-Robots-Tag: '.$data['robots'];
			}
			
			// Robots data is also returned to views
			if(isset($data['frame-permissions']) && $data['frame-permissions']){
				$data['www-set-header'][]='X-Frame-Options: '.$data['frame-permissions'];
			} elseif($tmp=$this->getState('frame-permissions')){
				$data['www-set-header'][]='X-Frame-Options: '.$tmp;
			}
			
			// Content security policy headers
			if(isset($data['content-security-policy']) && $data['content-security-policy']){
				$data['www-set-header'][]='X-Content-Security-Policy: '.$data['content-security-policy'];
			} elseif($tmp=$this->getState('content-security-policy')){
				$data['www-set-header'][]='X-Content-Security-Policy: '.$tmp;
			}
			
		// USER PERMISSIONS CHECKS
		
			// This is the best place to build your authentication module for web views
			// But the commands that it uses are not shown here, so it is commented out
			// This is essentially the boilerplate startpoint for you to implement authentication, as the actual login redirection is turned off
			// Attempting to get user session
			// if(isset($data['permissions'])){
			
				// Permissions are exploded into an array from comma separated string in sitemap file
				// $data['permissions']=explode(',',$data['permissions']);
				// This method automatically removes all empty entries from the array
				// $data['permissions']=array_filter($data['permissions']);
				
				// This flag, if changed, will redirect user to log-in screen
				// $failed=false;
				
				// Testing if user session exists
				// $user=$this->getUser();
				// if($user){
					// Double-checking user account validity from database
					// $userData=$this->dbSingle('SELECT * FROM users WHERE id=? AND deleted=0',array($user['id']));
					// if($userData){
						// Updating user session data from database (good if it has changed)
						// $this->setUser($userData);
						// Setting user permissions based on most recent information from database
						// $this->setPermissions(explode(',',$userData['permissions']));
						// Testing if permissions are included
						// if(!empty($data['permissions']) && !$this->checkPermissions($data['permissions'])){
							// $failed=true;
						// }
					// } else {
						// $failed=true;
					// }
				// } else {
					// $failed=true;
				// }
				
				// if($failed){
					// $siteMapReference=$this->getSitemap();
					// Success array is used since technically URL solving has been a 'success'
					// return $this->resultFalse('Authentication required',array('www-temporary-redirect'=>$siteMapReference['login']['url']));
				// }
				
			// }
		
		// Data about the view is returned
		return $data;
		
	}

}
	
?>