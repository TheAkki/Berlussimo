<?php

namespace App\Http\Controllers\Api\v1\Modules;


use App\Http\Controllers\Controller;
use App\Http\Requests\Legacy\PersonenRequest;
use App\Http\Requests\Modules\Persons\MergeRequest;
use App\Jobs\MergePersons;
use App\Models\DetailCategory;
use App\Models\DetailSubcategory;
use App\Models\Person;
use App\Services\ListViewsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;

class PersonController extends Controller
{
    public function index(PersonenRequest $request, ListViewsService $listViewsService)
    {
        $builder = Person::query();

        list($columns, $paginator, $index, $wantedRelations) = $listViewsService->calculateResponseData($request, $builder);

        return response()->json($listViewsService->response($columns, $index, $wantedRelations, $paginator, Person::class));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param PersonenRequest $request
     * @param Person $person
     * @return \Illuminate\Http\Response
     */
    public function update(PersonenRequest $request, Person $person)
    {
        $person->update($request->only(['name', 'first_name', 'birthday']));
        if ($request->exists('sex')) {
            $person->sex = $request->input('sex');
        }
        return response()->json($person);
    }

    public function store(PersonenRequest $request)
    {
        $person = Person::create($request->only(['name', 'first_name', 'birthday']));
        if ($request->exists('sex')) {
            $person->sex = $request->input('sex');
        }
        return response()->json($person);
    }

    public function merge(MergeRequest $request, Person $left, Person $right)
    {
        $this->dispatch(new MergePersons($request->only(['name', 'first_name', 'birthday', 'sex']), $left, $right));
        return response()->json(['status' => 'ok']);
    }

    public function show(PersonenRequest $request, Person $person)
    {
        $person->load([
            'phones',
            'emails',
            'faxs',
            'adressen',
            'hinweise',
            'mietvertraege.einheit.haus.objekt',
            'kaufvertraege.einheit.haus.objekt',
            'commonDetails',
            'jobsAsEmployee' => function ($query) {
                $query->with(['title', 'employer', 'employee']);
            },
            'roles',
            'audits' => function ($query) {
                $query->with('user');
            },
            'roles',
            'credential' => function ($query) {
                $query->withTrashed();
            }
        ]);
        return $person;
    }

    public function notifications(PersonenRequest $request, Person $person)
    {
        return $person->notifications;
    }

    public function notificationsMarkAllAsRead(PersonenRequest $request, Person $person)
    {
        return $person->notifications()->where('read_at', null)->update(['read_at' => new Carbon()]);
    }

    /**
     * @param PersonenRequest $request
     * @return mixed
     */
    public function detailsCategories(PersonenRequest $request)
    {
        return response()->json(
            DetailCategory::where('DETAIL_KAT_KATEGORIE', array_search(Person::class, Relation::morphMap()))
                ->with('subcategories')->defaultOrder()->get()
        );
    }

    /**
     * @param PersonenRequest $request
     * @return mixed
     */
    public function detailsSubcategories(PersonenRequest $request, $category)
    {
        return response()->json(
            DetailSubcategory::whereHas('category', function ($query) use ($category) {
                $query->where(
                    'DETAIL_KAT_KATEGORIE',
                    array_search(Person::class, Relation::morphMap())
                )->where(
                    'DETAIL_KAT_NAME',
                    $category
                );
            })->defaultOrder()->get()
        );
    }

    public function roles(PersonenRequest $request, Person $person)
    {
        return response()->json($person->roles);
    }

    public function parameters(PersonenRequest $request, ListViewsService $listViewsService)
    {
        return response()->json($listViewsService->getParameters(PersonController::class . '@index'));
    }
}