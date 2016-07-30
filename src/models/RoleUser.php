<?php

namespace KevinOrriss\UserRoles\Models;

use App;
use Exception;
use InvalidArgumentException;
use KevinOrriss\UserRoles\Models\Role;
use KevinOrriss\UserRoles\Models\RoleGroup;

trait RoleUser
{
    /**
     * All the roles that the user has assigned, the keys of the 
     * array are the role names.
     *
     * @var Role[]
     */
    protected $roles;

    /**
     * Returns the Role objects that have been directly assigned to this user
     * This is not recursive call, to get all roles a user has, use allRoles
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany('KevinOrriss\UserRoles\Models\Role', 'user_roles', 'user_id', 'role_id');
    }

    /**
     * Returns the RoleGroup objects that belong to this User.
     * This is not recursive
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roleGroups()
    {
        return $this->belongsToMany('KevinOrriss\UserRoles\Models\RoleGroup', 'user_role_groups', 'user_id', 'role_group_id');
    }

    /**
     * Loads all the roles the user has, directly or from role groups, and stores
     * each Role instance in the roles property.
     *
     * @return void
     */
    protected function loadRoles()
    {
        // set roles to be an empty array
        $this->roles = array();

        // load the roles directly assigned to the user
        $local_roles = $this->roles()->get();
        foreach($local_roles as $local_role)
        {
            $this->roles[$local_role->name] = $local_role;
        }

        // load roles from the role groups assigned to the user
        $role_groups = $this->roleGroups()->get();
        foreach($role_groups as $role_group)
        {
            $this->roles +=  $role_group->allRoles();
        }
    }

    /**
     * Returns the roles property which contains all the roles 
     * the user has been assigned directly or indirectly via
     * role groups.
     *
     * @return Role[]
     */
    public function allRoles()
    {
        if (is_null($this->roles))
        {
            $this->loadRoles();
        }
        return $this->roles;
    }

    /**
     * Returns if the RoleUser contains the given Role. This 
     * function is recursive and searches for the Role within
     * the sub RoleUser RoleGroups.
     *
     * @param string | string[] | Role | Role[] $role
     * @param string $match
     *
     * @throws InvalidArgumentException
     *
     * @return boolean
     */
    public function hasRole($role, $match = 'all')
    {
        $names = array();

        // handle the parameter type
        if (is_string($role))
        {
            $names[] = $role;
        }
        else if (is_a($role, "KevinOrriss\UserRoles\Models\Role"))
        {
            $names[] = $role->name;
        }
        else if (is_array($role))
        {
            foreach($role as $value)
            {
                if (is_string($value))
                {
                    $names[] = $value;
                }
                else if (is_a($value, "KevinOrriss\UserRoles\Models\Role"))
                {
                    $names[] = $value->name;
                }
                else
                {
                    throw new InvalidArgumentException('Parameter [$role] array can contain only Role instances or strings of role names');
                }
            }
        }
        else
        {
            throw new InvalidArgumentException('Parameter [$role] must be a Role instance or string of the role name');
        }

        // remove duplicates
        $names = array_unique($names);

        // if the match type param is not a string
        if (!is_string($match))
        {
            throw new InvalidArgumentException('Parameter [$match] must be a string with a value of "all" or "any"');
        }

        // convert the match to lower case
        $match = strtolower($match);

        // check the value is acceptable
        if (!in_array($match, ['any', 'all']))
        {
            throw new InvalidArgumentException('Parameter [$match] must have a value of "all" or "any"');
        }

        // get roles if havent already
        if (is_null($this->roles))
        {
            $this->loadRoles();
        }

        // store the number of found roles
        $count = 0;

        // search for the role names and keep a count of the matches
        foreach($names as $name)
        {
            if (array_key_exists($name, $this->roles))
            {
                $count++;
            }
        }

        // if we are matching all roles
        if ($match == 'all')
        {
            // ensure the match count is the same as the number of role names provided
            return $count == count($names);
        }
        // if matching any role
        else if ($match == 'any')
        {
            // check wqe found at least one role
            return $count > 0;
        }

        // unreachable code (theoretically)
        throw new Exception('Unhandled match type');
    }

    /**
     * Checks the user has the given role name, if the user does not, then
     * a 403 page will be displayed
     *
     * @param string $role
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    public function checkRole($role, $match = 'all')
    {
        // display a 403 page if the user does not have the given role
        if (!$this->hasRole($role, $match))
        {
            App::abort(403);
        }
    }
}