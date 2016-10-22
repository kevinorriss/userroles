<?php

namespace KevinOrriss\UserRoles\Models;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoleGroup extends Model
{
    /**
     * When deleting the model, it is not actually removed from the database, instead
     * the deleted_at column has the current time set
     */
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * Returns the Role objects that belong to this RoleGroup.
     * This is not recursive
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany('KevinOrriss\UserRoles\Models\Role', 'role_group_roles', 'role_group_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Returns the Role objects that belong to this RoleGroup.
     * This is recursive
     *
     * @return Role[]
     */
    public function allRoles()
    {
        // array to return
        $all_roles = array();

        // local roles
        $roles = $this->roles()->get();
        foreach($roles as $role)
        {
            $all_roles[$role->name] = $role;
        }

        // children roles
        $children = $this->children()->get();
        foreach($children as $child)
        {
            $all_roles += $child->allRoles();
        }

        // return all roles
        return $all_roles;
    }

    /**
     * Returns the Model objects, specified in config('userroles.user_model'), that this Role belongs to.
     * This is not recursive
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(config('userroles.user_model'), 'user_role_groups', 'role_group_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Returns the RoleGroup objects that this RoleGroup belongs to.
     * This is not recursive
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function parents()
    {
        return $this->belongsToMany('KevinOrriss\UserRoles\Models\RoleGroup', 'role_group_groups', 'sub_role_group_id', 'role_group_id')
            ->withTimestamps();
    }

    /**
     * Returns the RoleGroup objects that belong to this RoleGroup.
     * This is not recursive
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function children()
    {
        return $this->belongsToMany('KevinOrriss\UserRoles\Models\RoleGroup', 'role_group_groups', 'role_group_id', 'sub_role_group_id')
            ->withTimestamps();
    }

    /**
     * Returns if the Role Group contains the given Role. This 
     * function is recursive and searches for the Role within
     * the sub Role Groups.
     *
     * @param string | string[] | Role | Role[] $role
     *
     * @return boolean
     */
    public function hasRole($role)
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
                if (is_string($role))
                {
                    $names[] = $value;
                }
                else if (is_a($role, "KevinOrriss\UserRoles\Models\Role"))
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

        // check if this Role Group has the given role
        $can = count($this->roles()->whereIn('roles.name', $names)->first()) > 0;
        if ($can) { return TRUE; }

        // recursively call this function on all sub Role Groups
        $children = $this->children()->get();
        foreach($children as $child)
        {
            if ($child->hasRole($names)) { return TRUE; }
        }

        // role not found
        return FALSE;
    }

    /**
     * Returns an array of validation rules used for the RoleGroup model. If an id
     * of a RoleGroup is passed, then any unique constraint will be ignored for the
     * given id. Used for updates.
     *
     * @param int|NULL $id
     *
     * @throws InvalidArguementException
     *
     * @return string[]
     */
    public static function rules($id=NULL)
    {
        if (!is_null($id) && !is_int($id))
        {
            throw new InvalidArguementException('Param [$id] is expected to be NULL or an Integer');
        }

        return [
            'name' => 'bail|required|min:3|max:50|regex:#^[a-z]+(_[a-z]+)*$#|unique:role_groups,name' . (!is_null($id) ? ",".$id : ""),
            'description' => 'bail|required|min:10'];
    }

    /**
     * Returns an array of validation messages to use on failure.
     * These are custom messages. This array is not a class constant
     * purely for uniform code when used with the rules() function.
     *
     * @return string[]
     */
    public static function messages()
    {
        return [
            'name.regex' => 'Name can contain only lower case a-z seperated by single underscores'];
    }
}
