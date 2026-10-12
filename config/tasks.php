<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Task module settings
|--------------------------------------------------------------------------
| "relations" tells the Task module which of YOUR existing models to link to.
| Adjust the class name and the column(s) used as the display label so they match
| your project. Several columns are joined with a space (e.g. first_name + last_name).
| A relation whose class does not exist is skipped (its dropdown is just empty).
*/

return [
    'relations' => [
        'company' => ['model' => \App\Models\Company::class, 'columns' => ['name']],
        'contact' => ['model' => \App\Models\Contact::class, 'columns' => ['first_name', 'last_name']],
        'opportunity' => ['model' => \App\Models\Opportunity::class, 'columns' => ['name']],
        'quote' => ['model' => \App\Models\Quote::class, 'columns' => ['name']],
    ],

    /** Column on the users table shown in the Assignee dropdown. */
    'user_label' => 'name',

    /** Max rows returned per dropdown by GET /tasks/lookups. */
    'lookup_limit' => 500,

    /** Created by TaskOptionsSeeder (only when missing). [name, color, is_default, is_closed] */
    'default_types' => [
        ['Call', '#2563eb'], ['Email', '#7c3aed'], ['Meeting', '#0891b2'], ['Follow-up', '#d97706'], ['To-do', '#475467'],
    ],
    'default_priorities' => [
        ['Low', '#667085'], ['Medium', '#2563eb', true], ['High', '#d97706'], ['Urgent', '#dc2626'],
    ],
    'default_statuses' => [
        ['Not Started', '#667085', true], ['In Progress', '#2563eb'], ['Waiting', '#d97706'],
        ['Completed', '#16a34a', false, true], ['Cancelled', '#98a2b3', false, true],
    ],
];
