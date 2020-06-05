<?php
function route_class()
{
    return str_replace('.', '-', Route::currentRouteName());
}

function ngrok_route($routeName, $routeParams = [])
{
    if (app()->environment('local') && $ngrokUrl = config('app.ngrok_url')) {
        return $ngrokUrl . route($routeName, $routeParams, false);
    }

    return route($routeName, $routeParams, true);
}