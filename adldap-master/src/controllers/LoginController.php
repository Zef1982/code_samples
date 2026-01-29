<?php 

namespace estdigital\adldap\controllers;

use Craft;
use craft\db\Query;
use craft\web\Controller;
use craft\elements\User;
use craft\services\UserGroups;
use craft\services\Elements;
use estdigital\adldap\Adldap;
use yii\web\IdentityInterface;

/**
 * AdldapController
 */
class LoginController extends Controller
{

    // Make login available to guests!
    protected $allowAnonymous = true;

    /**
     * Method to Authenticate user trying to login.
     * @throws \Adldap\Exceptions\Auth\BindException
     */
    public function actionIndex()
    {
        $this->requirePostRequest();
		$craft = \Craft::$app;

        $member_username = $craft->request->getBodyParam('username');
        $member_password = $craft->request->getBodyParam('password');
        $redirect = $craft->request->getBodyParam('redirect');
        
        $settings = $craft->plugins->getPlugin('adldap')->getSettings();
        $group_handle = $settings['group'];

        $config = [            
            "hosts"             => explode(",", $settings['domainControllers']),
            "base_dn"           => $settings['baseDN'],
            'port'              => $settings['port'],
            "use_ssl"           => boolval($settings['ssl']),
            "use_tls"           => boolval($settings['tls']),
            "follow_referrals"  => boolval($settings['referrals']),
        ];

        // Start the LDAP connection with the configuration from the settings.
        $ad = new \Adldap\Adldap();
        $ad->addProvider($config);

        try{

            // Connect.
            $provider = $ad->connect();
            
            try {

                // Search the username.
                $ldap_user = $provider->search()->where('cn', '=', $member_username)->firstOrFail();

                try{

                    // Authenticate the user.
                    if ($provider->auth()->attempt($ldap_user, $member_password, true)) {

                        // Check if it's an existing user.
                        $result = (new Query)->select('id')->from('cr_users')->where(['username' => $member_username ])->one();

                        if(empty($result)){ 

                            // Create a new user.                          
                            $user = new User();
                            $user->email = $member_username . "@toolkit.tld";
                            $user->friendlyName = $ldap_user->getFirstAttribute('fullname');
                            $user->username  = $member_username;
                            $user->password  = $member_password;
                            $user->archived = FALSE;
                            $user->pending  = FALSE;
                            $user->suspended = FALSE;
                            $user->locked = FALSE;
                            $user->admin = FALSE;
                            $user->firstSave = TRUE;
                            $user->passwordResetRequired = FALSE;
                            $user->lastLoginDate =date("Y-m-d H:i:s");
                            $user->dateCreated = date("Y-m-d H:i:s");
                            $user->dateUpdated = date("Y-m-d H:i:s");

                            // Save the new user.
                            if($craft->elements->saveElement($user)){
                            
                                // Add user to the appropriate user group.
                                $user_group_id = (new Query)->select('id')->from('cr_usergroups')->where(['handle'=>$group_handle])->one()['id'];
                                $craft->users->assignUserToGroups($user->id, [$user_group_id]);

                            } else {
                                $message = "";
                                foreach($user->getErrors() as $key => $errors){
                                    foreach($errors as $error){
                                        $message .= $error;
                                    }
                                }
                                $craft->session->setFlash('errorMessage', 'CraftCMS could not save the user: ' . $message);
                                return;
                            }

                        }else { 

                            // Update new password if neccesary.
                            $id = $result['id'];
                            $user = $craft->users->getUserById( $id );
                            $user->newPassword  = $member_password;
                            $craft->elements->saveElement($user);
                        }

                        // Login the user & redirect!
                        $craft->getUser()->loginByUserId($user->id);
                        $this->redirect($redirect);

                    }else {
                        // Credentials were incorrect.
                        $craft->session->setFlash('errorMessage', 'The Credentials given were not accepted / valid.');
                    }

                } catch (\Adldap\Auth\PasswordRequiredException $e) {
                    // The user didn't supply a password.
                    $craft->session->setFlash('errorMessage', $e->getMessage());
                }
    
            } catch (\Adldap\Exceptions\Auth\BindException $e) {
                $craft->session->setFlash('errorMessage', 'Can\'t find username in ULCN.');
            }
            catch (\Adldap\Models\ModelNotFoundException $e) {
                $craft->session->setFlash('errorMessage', 'Can\'t find username in ULCN.');
            }

        } catch (\Adldap\Auth\BindException $e) {
            $craft->session->setFlash('errorMessage', $e->getMessage());
        }

    
    }

}