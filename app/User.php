<?php

/**
 * User Model
 *
 * @category   User
 * @package    MavBasic-Models
 * @author     Sachin Pawaskar<spawaskar@unomaha.edu>
 * @copyright  2016-2017
 * @license    The MIT License (MIT)
 * @version    GIT: $Id$
 * @since      File available since Release 1.0.0
 */

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Zizaco\Entrust\Traits\EntrustUserTrait;

use App\Http\Traits\SettingsTrait;
use App\Http\Traits\AuditsTrait;
use App\Scopes\OrgScope;
use App\Eula;
use Log;
use Session;


/**
 * Class User
 * @package App
 * @author  Sachin Pawaskar<spawaskar@unomaha.edu>
 * @since   File available since Release 1.0.0
 */
class User extends Authenticatable
{
    use Notifiable;
    use EntrustUserTrait;
    use SoftDeletes { SoftDeletes::restore insteadof EntrustUserTrait; }
    use SettingsTrait;
    use AuditsTrait;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at', 'last_login_at', 'expiration_at', 'password_change_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'org_id','name', 'email', 'password', 'active', 'phone', 'default_language', 'default_country', 'last_login_ip_address',
        'last_login_device', 'number_of_logins', 'last_login_at', 'expiration_at', 'password_change_at',

        'created_by', 'updated_by',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public $wizard = array('mode' => 'false', 'displaytabs' => 'false', 'modal' => 'true', 'heading' => 'Welcome to SPawaskar Wizard');
    public $wizardHelp = array('mode' => 'false', 'displaytabs' => 'true', 'modal' => 'false', 'heading' => 'Application Help Wizard');
    public $wizardTabs = array('Eula' => ['name' => 'Eula', 'active' => true], 'Welcome' => ['name' => 'Welcome', 'active' => false]);
    public $wizardHelpTabs = array('Eula' => ['name' => 'Eula', 'active' => true], 'Welcome' => ['name' => 'Welcome', 'active' => false]);

    // Eula related
    public $eulaProcessing = false;
    public $eulaAccepted = false;
    public static $eulaActiveSystemList = [];
    public $userAcceptedEula = null;

    public $passwordChangeRequested = false;
    public $showStartupWizard = false;

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

//        static::addGlobalScope(new OrgScope);
    }
    /**
     * Get a List of roles ids associated with the current user.
     *
     * @return array
     */
    public function getRoleListAttribute()
    {
        return $this->roles->pluck('id')->all();
    }

    /**
     * Return true if the User has an Administration Role, false otherwise
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    /**
     * Return true if the User has an Administration Role, false otherwise
     *
     * @return bool
     */
    public function isAdministrator()
    {
        return $this->isAdmin();
    }

    /**
     * Return true if the User has an sysadmin Role, false otherwise
     *
     * @return bool
     */
    public function isSystemAdmin()
    {
        return $this->hasRole('sysadmin');
    }

    /**
     * Return true if the User has an sysadmin Role, false otherwise
     *
     * @return bool
     */
    public function isSystem()
    {
        return $this->isSysAdmin();
    }

    /**
     * Return true if the User is Active, false otherwise
     *
     * @return mixed
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Scope a query to only include users of a given org.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $org
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfOrg($query, $org)
    {
        return $query->where('org_id', $org->id);
    }

    /**
     * Get the org that this user belongs to.
     */
    public function org()
    {
        return $this->belongsTo('App\Org');
    }

    /**
     * Get all of the settings that belong to this user.
     */
    public function settings()
    {
        return $this->morphToMany('App\Setting', 'settingable')
            ->withPivot('settingable_id', 'settingable_type', 'setting_id', 'value', 'json_values', 'created_by', 'updated_by')
            ->withTimestamps();
    }

    /**
     * Get all of the eulas for this user.
     */
    public function eulas()
    {
        return $this->belongsToMany('App\Eula', 'eula_user', 'user_id', 'eula_id')
            ->withPivot('user_id', 'eula_id', 'signature', 'accepted_at', 'created_by', 'updated_by')
            ->withTimestamps();
    }

    /**
     * Get the latest accepted eula for this user.
     */
    public function getActiveEula()
    {
        return $this->eulas()->orderBy('pivot_accepted_at', 'desc')->get()->first();
    }

    /**
     * The checkEula function sets the eulaProcessing and eulaAccepted flags for this user.
     * This function is called to check if Eula processing is required for this user
     * once the user logs into the system.
     * @return bool
     */
    public function checkEula()
    {
        if ($this->org->getSettingValue('eula_processing')) {
            $this->eulaProcessing = true;
            $orgEula = $this->org->getActiveEulaForUser($this);
            $currentlyAcceptedEula = $this->getActiveEula();

            if (!isset($currentlyAcceptedEula) || !isset($orgEula)) {
    //            dd(['true',$orgEula, $currentlyAcceptedEula, $this]);
                return ($this->eulaAccepted = false);
            } else if ($orgEula->id != $currentlyAcceptedEula->id) {
                return ($this->eulaAccepted = false);
            } else {
                return ($this->eulaAccepted = true);
            }
        } else {
            $this->eulaProcessing = false;
        }
    }

    /**
     * check if wizard should be displayed on startup
     */
    public function buildWizardStartup() {
        $modal = 'true';
        $this->wizardStartup = $this->wizardStartupTabs = [];

        $this->checkEula(); // sets the eulaProcessing && eulaAccepted flags

        // First check to see if we need to display EULA
        if ($this->eulaProcessing && !$this->eulaAccepted) {
            if ($this->org->getActiveEulaForUser($this) != null) {
                $this->wizardStartupTabs = array_merge($this->wizardStartupTabs,
                    array('Eula' => ['key' => 'Eula', 'name' => trans('labels.eula'), 'src' => '\eula']));
            }
        }
        count($this->wizardStartupTabs) ? $modal = 'true' : $modal = 'false'; // which mean we have Eula tab
        $startTab = (count($this->wizardStartupTabs) == 1) ? 'Eula' : ''; // which mean we have Eula tab

        // Second check to see if we need to display Change Password
        if ($this->passwordChangeRequested) {
            $this->wizardStartupTabs = array_merge($this->wizardStartupTabs,
                array('ChangePassword' => ['key' => 'ChangePassword', 'name' => trans('labels.change_password'), 'src' => '\passwordChangeOnLogin']));
            if (empty($startTab)) { $startTab = 'ChangePassword'; }
        }

        if ($this->getSettingValue('welcome_screen_on_startup'))
        {
            $this->wizardStartupTabs = array_merge($this->wizardStartupTabs,
                array('Welcome' => ['key' => 'Welcome', 'name' => trans('labels.welcome'), 'src' => $this->org->getSettingValue('welcome_screen_url')]));
            if (empty($startTab)) { $startTab = 'Welcome'; }
        }

        if (count($this->wizardStartupTabs)) {
            $this->wizardStartup = array('wizardType'=>'Startup', 'mode'=>'false', 'displaytabs'=>'false',
                'startTab'=>$startTab, 'modal'=>$modal, 'heading'=>trans('messages.welcome_user', ['name'=>$this->name]));
        }
        $this->showStartupWizard = count($this->wizardStartupTabs) ? true:false;
        Log::info('User.buildWizardStartup: wizardStartupTabs='.json_encode($this->wizardStartupTabs));
        Log::info('User.buildWizardStartup: wizardStartup='.json_encode($this->wizardStartup));
        return count($this->wizardStartupTabs);
    }

    /**
     * check if wizard should be displayed on startup
     */
    public function buildWizardHelp() {
        $this->wizardHelp = $this->wizardHelpTabs = [];

        $this->wizardHelpTabs = array_merge($this->wizardHelpTabs, array('About' => ['key' => 'About', 'name' => trans('labels.about'), 'src' => '\about']));
        $this->wizardHelpTabs = array_merge($this->wizardHelpTabs, array('AboutBrowser' => ['key' => 'AboutBrowser', 'name' => trans('labels.aboutbrowser'), 'src' => '\aboutbrowser']));
        $this->wizardHelpTabs = array_merge($this->wizardHelpTabs, array('Welcome' => ['key' => 'Welcome', 'name' => trans('labels.welcome'), 'src' => $this->org->getSettingValue('welcome_screen_url')]));

        if ($this->eulaProcessing && $this->eulaAccepted) {
            if ($this->org->getActiveEulaForUser($this) != null) {
                $this->wizardHelpTabs = array_merge($this->wizardHelpTabs, array('Eula' => ['key' => 'Eula', 'name' => trans('labels.eula'), 'src' => '\eula']));
            }
        }

        if (count($this->wizardHelpTabs)) {
            $this->wizardHelp = array('wizardType'=>'Help', 'mode'=>'false', 'displaytabs'=>'true',
                'startTab'=>'About', 'modal'=>'false', 'heading'=>config('app.name', 'MavBasic') .' - '. trans('labels.help'));
        }
        Log::info('User.buildWizardHelp: wizardHelpTabs='.json_encode($this->wizardHelpTabs));
        Log::info('User.buildWizardHelp: wizardHelp='.json_encode($this->wizardHelp));
        return count($this->wizardHelpTabs);
    }

    public function onSettingChange()
    {
        $this->buildWizardHelp();
        Session::put('user', $this);
    }
}
