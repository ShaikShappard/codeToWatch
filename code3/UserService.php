<?php

namespace App\Services;

use App\Exports\SingleUserPdfExport;
use App\Exports\UserExport;
use App\Exports\UserInvoicePdfExport;
use App\Exports\UserListPdfExport;
use App\Facades\SiteSettings;
use App\Http\Resources\user\MemberInvoiceResource;
use App\Http\Resources\user\UserResource;
use App\Http\Resources\user\UserRolesResource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Interfaces\BillsbyServiceInterface;
use App\Services\Interfaces\UserServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use JamesDordoy\LaravelVueDatatable\Http\Resources\DataTableCollectionResource;
use Spatie\Permission\Models\Role;

/**
 * Class UserService
 * @package App\Services
 */
class UserService extends Service implements UserServiceInterface
{
    const SEARCHABLE_FIELDS = [
        'name' ,
        'email' ,
        'active' ,
    ];

    public $user;

    private $fileManager;

    /**
     * UserService constructor.
     * @param FileManager $fileManager
     */
    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    /**
     * Query to base
     *
     * @param $request
     */
    public function getUserList($request)
    {
        $this->filteredData = User::role(SiteSettings::customerRole());

        $data = $request->all();
        $this->searching(self::SEARCHABLE_FIELDS, $request->search);
        $this->filterByDate($request->dates);

        $this->filterByPeriod($request->joined_from, $request->joined_to, $request->expires_from, $request->expires_to);

        //sorting
        if ($sort = is_string($data['sort']) ? json_decode($data['sort'], true) : $data['sort']) {
            if ($sort['column'] == 'plan') {
                $this->filteredData->leftJoin('purchased_plans as pp', function ($join) {
                    $join->on('pp.user_id', '=', 'users.id')
                        ->leftJoin('billing_plans as bp', function ($join) {
                            $join->on('bp.id', '=', 'pp.plan_id');
                        });
                })->select('users.*')->orderBy('bp.name', $sort['dir']);

            } elseif ($sort['column'] == 'downloads') {
                $this->filteredData->withCount('downloads')->orderBy('downloads_count', $sort['dir']);

            } elseif ($sort['column'] == 'TD') {

                $this->filteredData->leftJoin('purchased_plans as pp', function ($join) {
                    $join->on('pp.user_id', '=', 'users.id');
                })->select('users.*')->orderBy('pp.created_at', $sort['dir']);

            } elseif ($sort['column'] == 'status') {
                $this->filteredData->leftJoin('purchased_plans as pp', function ($join) {
                    $join->on('pp.user_id', '=', 'users.id');
                })->select('users.*')->orderBy('pp.status_id', $sort['dir']);

            } elseif ($sort['column'] == 'status') {
                $this->filteredData->orderBy('active', $sort['dir']);

            } elseif ($sort['column'] == 'joined') {
                $this->filteredData->orderBy('created_at', $sort['dir']);

            } elseif ($sort['column'] == 'expires') {
                $this->filteredData->orderBy('membership_expires', $sort['dir']);

            } elseif ($sort['column'] == 'total_days_member'){
                $this->filteredData->orderBy('active_membership_days', $sort['dir']);

            } else {
                $this->filteredData->orderBy($sort['column'], $sort['dir']);
            }
        }
    }

    /**
     * Get user by id
     *
     * @param $id
     * @return mixed
     */
    public function getUser($id)
    {
        return $this->user = User::find($id);
    }

    /**
     * Create user
     *
     * @param $request
     * @return mixed
     */
    public function createUser($request)
    {
        return $this->user = User::create([
            'name' => $request['name'],
            'email' => $request['email'],
            'phone' => $request['phone'] ?? null,
            'business_type_id' => $request['typeId'] ?? null,
            'password' => Hash::make($request['password'] ?? Str::random(8))
        ])->assignRole('customer');
    }

    /**
     * Update user
     *
     * @param $request
     */
    public function updateUser($request)
    {
        $this->user->update($request->except('password'));

        if ($request->password_confirmation)
            $this->user->update(['password' => Hash::make($request->password)]);

        $this->user->roles()->detach();
        $this->user->assignRole($request->role);
    }

    /**
     * Create or update user
     *
     * @param $request
     * @return mixed
     */
    public function setUser($request)
    {
        if ($request->id)
            $this->user = User::find($request->id);
        else
            $this->createUser($request);

        $this->updateUser($request);

        return $this->user;
    }

    /**
     * Upload img
     *
     * @param $id
     * @param $file
     * @return bool
     * @throws \Exception
     */
    public function uploadImg($id, $file)
    {
        return $this->fileManager->uploadImgMediaLib($id, User::class, $file);
    }

    /**
     * Delete img
     *
     * @param $id
     * @return bool
     * @throws \Exception
     */
    public function deleteImg($id)
    {
        return $this->fileManager->deleteImgMediaLib($id, User::class);
    }

    /**
     * Export filtered list in csv
     *
     * @return UserExport
     */
    public function exportAll()
    {
        return new UserExport(User::role(SiteSettings::customerRole())->get());
    }

    /**
     * Export filtered list in pdf
     *
     * @return UserListPdfExport
     */
    public function exportAllPdf()
    {
        return new UserListPdfExport(User::role(SiteSettings::customerRole())->get());
    }

    /**
     * Export list in csv
     *
     * @param $request
     * @return UserExport
     */
    public function exportList($request)
    {
        $this->getUserList($request);

        return new UserExport($this->filteredData->get());
    }

    /**
     * Export list in pdf
     *
     * @param $request
     * @return UserListPdfExport
     */
    public function exportListPdf($request)
    {
        $this->getUserList($request);

        return new UserListPdfExport($this->filteredData->get());
    }

    /**
     * Export row in csv
     *
     * @param $id
     * @return UserExport
     */
    public function exportRow($id)
    {
        $tracks = User::where('id', $id)->get();

        return new UserExport($tracks);
    }

    /**
     * @param Transaction $transaction
     * @return UserInvoicePdfExport
     */
    public function exportInvoice($id)
    {
        return new UserInvoicePdfExport(Transaction::where('id', $id)->get()->loadMissing(['order', 'billingPlan']));
    }

    /**
     * @param $id
     * @return SingleUserPdfExport
     */
    public function exportSingleUser($id)
    {
        return new SingleUserPdfExport(User::where('id', $id)->get());
    }

    /**
     * Get all roles in system
     *
     * @return UserRolesResource
     */
    public function getRoles()
    {
        return new UserRolesResource(Role::all());
    }

    /**
     * Check if this user exist or create new user
     * @param $request
     * @return bool
     * @deprecated
     *
     */
    public function createOrCheck($request)
    {
        $user = User::where('email', $request['email'])->orWhere('phone', $request['phone'])->first();

        if ($user == null)
            return $this->createUser($request);
        else return false;

    }

    /**
     * Create with validate
     *
     * @param $data
     * @return array
     * @throws \Exception
     */
    public function createWithValidatorAndAuth($data)
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails())
            throw  new \Exception($validator->errors());

        $this->createUser($data);

        Auth::login($this->user, true);

        return $this->user;
    }

    /**
     * Return artists
     *
     * @param array $artists
     * @return mixed
     */
    public function getArtists($artists = [])
    {
        if (count($artists) > 0)
            $artists = $artists->keyBy('id');
        else
            $artists = collect($artists);

        return User::whereHas('roles', function ($q) {
            $q->where('name', SiteSettings::artistRole());
        })->get()->map(function ($item) use ($artists) {
            return [
                'title' => $item->artist_name,
                'id' => $item->id,
                'checked' => $artists->has($item->id) ? true : false,
                'visible' => true,
            ];
        });
    }

    /**
     * @return int
     */
    public function switchTheme()
    {
        if (Auth::user()->theme == 0)
            $theme = 1;
        else
            $theme = 0;

        Auth::user()->update(['theme' => $theme]);
        return $theme;
    }
}
