<?php

namespace App\Models\Azure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Ohtarr\Azure\AzureApp;

class Azure extends Model
{

    public static function getToken($scope)
    {
        $token = Cache::store('cache_general')->get('msoauth_token_' . $scope);
        if($token)
        {
            return $token;
        }
        $app = new AzureApp(env('AZURE_AD_TENANT_ID'),env('AZURE_AD_CLIENT_ID'),env('AZURE_AD_CLIENT_SECRET'));
        $array = $app->getRawToken($scope);

        Cache::store('cache_general')->put('msoauth_token_' . $scope, $array['access_token'], $array['expires_in']*.95);
        return $array['access_token'];
    }

}
