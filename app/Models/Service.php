<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Service extends BaseModel
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];
    public function type()
    {
        return 'service';
    }
    public function project()
    {
        return data_get($this, 'environment.project');
    }
    public function team()
    {
        return data_get($this, 'environment.project.team');
    }
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
    public function status()
    {
        $applications = $this->applications;
        $databases = $this->databases;

        $complexStatus = null;
        $complexHealth = null;

        foreach ($applications as $application) {
            if ($application->exclude_from_status) {
                continue;
            }
            $status = str($application->status)->before('(')->trim();
            $health = str($application->status)->between('(', ')')->trim();
            if ($complexStatus === 'degraded') {
                continue;
            }
            if ($status->startsWith('running')) {
                if ($complexStatus === 'exited') {
                    $complexStatus = 'degraded';
                } else {
                    $complexStatus = 'running';
                }
            } else if ($status->startsWith('restarting')) {
                $complexStatus = 'degraded';
            } else if ($status->startsWith('exited')) {
                $complexStatus = 'exited';
            }
            if ($health->value() === 'healthy') {
                if ($complexHealth === 'unhealthy') {
                    continue;
                }
                $complexHealth = 'healthy';
            } else {
                $complexHealth = 'unhealthy';
            }
        }
        foreach ($databases as $database) {
            if ($database->exclude_from_status) {
                continue;
            }
            $status = str($database->status)->before('(')->trim();
            $health = str($database->status)->between('(', ')')->trim();
            if ($complexStatus === 'degraded') {
                continue;
            }
            if ($status->startsWith('running')) {
                if ($complexStatus === 'exited') {
                    $complexStatus = 'degraded';
                } else {
                    $complexStatus = 'running';
                }
            } else if ($status->startsWith('restarting')) {
                $complexStatus = 'degraded';
            } else if ($status->startsWith('exited')) {
                $complexStatus = 'exited';
            }
            if ($health->value() === 'healthy') {
                if ($complexHealth === 'unhealthy') {
                    continue;
                }
                $complexHealth = 'healthy';
            } else {
                $complexHealth = 'unhealthy';
            }
        }
        return "{$complexStatus}:{$complexHealth}";
    }
    public function extraFields()
    {
        $fields = collect([]);
        $applications = $this->applications()->get();
        foreach ($applications as $application) {
            $image = str($application->image)->before(':')->value();
            switch ($image) {
                case str($image)?->contains('grafana'):
                    $data = collect([]);
                    $admin_password = $this->environment_variables()->where('key', 'SERVICE_PASSWORD_GRAFANA')->first();
                    $data = $data->merge([
                        'Admin User' => [
                            'key' => 'GF_SECURITY_ADMIN_USER',
                            'value' => 'admin',
                            'readonly' => true,
                            'rules' => 'required',
                        ],
                    ]);
                    if ($admin_password) {
                        $data = $data->merge([
                            'Admin Password' => [
                                'key' => 'GF_SECURITY_ADMIN_PASSWORD',
                                'value' => data_get($admin_password, 'value'),
                                'rules' => 'required',
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    $fields->put('Grafana', $data);
                    break;
                case str($image)?->contains('directus'):
                    $data = collect([]);
                    $admin_email = $this->environment_variables()->where('key', 'ADMIN_EMAIL')->first();
                    $admin_password = $this->environment_variables()->where('key', 'SERVICE_PASSWORD_ADMIN')->first();

                    if ($admin_email) {
                        $data = $data->merge([
                            'Admin Email' => [
                                'key' => data_get($admin_email, 'key'),
                                'value' => data_get($admin_email, 'value'),
                                'rules' => 'required|email',
                            ],
                        ]);
                    }
                    if ($admin_password) {
                        $data = $data->merge([
                            'Admin Password' => [
                                'key' => data_get($admin_password, 'key'),
                                'value' => data_get($admin_password, 'value'),
                                'rules' => 'required',
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    $fields->put('Directus', $data);
                    break;
                case str($image)?->contains('kong'):
                    $data = collect([]);
                    $dashboard_user = $this->environment_variables()->where('key', 'SERVICE_USER_ADMIN')->first();
                    $dashboard_password = $this->environment_variables()->where('key', 'SERVICE_PASSWORD_ADMIN')->first();
                    if ($dashboard_user) {
                        $data = $data->merge([
                            'Dashboard User' => [
                                'key' => data_get($dashboard_user, 'key'),
                                'value' => data_get($dashboard_user, 'value'),
                                'rules' => 'required',
                            ],
                        ]);
                    }
                    if ($dashboard_password) {
                        $data = $data->merge([
                            'Dashboard Password' => [
                                'key' => data_get($dashboard_password, 'key'),
                                'value' => data_get($dashboard_password, 'value'),
                                'rules' => 'required',
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    $fields->put('Supabase', $data->toArray());
                case str($image)?->contains('minio'):
                    $data = collect([]);
                    $console_url = $this->environment_variables()->where('key', 'MINIO_BROWSER_REDIRECT_URL')->first();
                    $s3_api_url = $this->environment_variables()->where('key', 'MINIO_SERVER_URL')->first();
                    $admin_user = $this->environment_variables()->where('key', 'SERVICE_USER_MINIO')->first();
                    if (is_null($admin_user)) {
                        $admin_user = $this->environment_variables()->where('key', 'MINIO_ROOT_USER')->first();
                    }
                    $admin_password = $this->environment_variables()->where('key', 'SERVICE_PASSWORD_MINIO')->first();
                    if (is_null($admin_password)) {
                        $admin_password = $this->environment_variables()->where('key', 'MINIO_ROOT_PASSWORD')->first();
                    }

                    if ($console_url) {
                        $data = $data->merge([
                            'Console URL' => [
                                'key' => data_get($console_url, 'key'),
                                'value' => data_get($console_url, 'value'),
                                'rules' => 'required|url',
                            ],
                        ]);
                    }
                    if ($s3_api_url) {
                        $data = $data->merge([
                            'S3 API URL' => [
                                'key' => data_get($s3_api_url, 'key'),
                                'value' => data_get($s3_api_url, 'value'),
                                'rules' => 'required|url',
                            ],
                        ]);
                    }
                    if ($admin_user) {
                        $data = $data->merge([
                            'Admin User' => [
                                'key' => data_get($admin_user, 'key'),
                                'value' => data_get($admin_user, 'value'),
                                'rules' => 'required',
                            ],
                        ]);
                    }
                    if ($admin_password) {
                        $data = $data->merge([
                            'Admin Password' => [
                                'key' => data_get($admin_password, 'key'),
                                'value' => data_get($admin_password, 'value'),
                                'rules' => 'required',
                                'isPassword' => true,
                            ],
                        ]);
                    }

                    $fields->put('MinIO', $data->toArray());
                    break;
                case str($image)?->contains('weblate'):
                    $data = collect([]);
                    $admin_email = $this->environment_variables()->where('key', 'WEBLATE_ADMIN_EMAIL')->first();
                    $admin_password = $this->environment_variables()->where('key', 'SERVICE_PASSWORD_WEBLATE')->first();

                    if ($admin_email) {
                        $data = $data->merge([
                            'Admin Email' => [
                                'key' => data_get($admin_email, 'key'),
                                'value' => data_get($admin_email, 'value'),
                                'rules' => 'required|email',
                            ],
                        ]);
                    }
                    if ($admin_password) {
                        $data = $data->merge([
                            'Admin Password' => [
                                'key' => data_get($admin_password, 'key'),
                                'value' => data_get($admin_password, 'value'),
                                'rules' => 'required',
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    $fields->put('Weblate', $data);
                    break;
                case str($image)?->contains('meilisearch'):
                    $data = collect([]);
                    $SERVICE_PASSWORD_MEILISEARCH = $this->environment_variables()->where('key', 'SERVICE_PASSWORD_MEILISEARCH')->first();
                    if ($SERVICE_PASSWORD_MEILISEARCH) {
                        $data = $data->merge([
                            'API Key' => [
                                'key' => data_get($SERVICE_PASSWORD_MEILISEARCH, 'key'),
                                'value' => data_get($SERVICE_PASSWORD_MEILISEARCH, 'value'),
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    $fields->put('Meilisearch', $data);
                    break;
                case str($image)?->contains('ghost'):
                    $data = collect([]);
                    $MAIL_OPTIONS_AUTH_PASS = $this->environment_variables()->where('key', 'MAIL_OPTIONS_AUTH_PASS')->first();
                    $MAIL_OPTIONS_AUTH_USER = $this->environment_variables()->where('key', 'MAIL_OPTIONS_AUTH_USER')->first();
                    $MAIL_OPTIONS_SECURE = $this->environment_variables()->where('key', 'MAIL_OPTIONS_SECURE')->first();
                    $MAIL_OPTIONS_PORT = $this->environment_variables()->where('key', 'MAIL_OPTIONS_PORT')->first();
                    $MAIL_OPTIONS_SERVICE = $this->environment_variables()->where('key', 'MAIL_OPTIONS_SERVICE')->first();
                    $MAIL_OPTIONS_HOST = $this->environment_variables()->where('key', 'MAIL_OPTIONS_HOST')->first();
                    if ($MAIL_OPTIONS_AUTH_PASS) {
                        $data = $data->merge([
                            'Mail Password' => [
                                'key' => data_get($MAIL_OPTIONS_AUTH_PASS, 'key'),
                                'value' => data_get($MAIL_OPTIONS_AUTH_PASS, 'value'),
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    if ($MAIL_OPTIONS_AUTH_USER) {
                        $data = $data->merge([
                            'Mail User' => [
                                'key' => data_get($MAIL_OPTIONS_AUTH_USER, 'key'),
                                'value' => data_get($MAIL_OPTIONS_AUTH_USER, 'value'),
                            ],
                        ]);
                    }
                    if ($MAIL_OPTIONS_SECURE) {
                        $data = $data->merge([
                            'Mail Secure' => [
                                'key' => data_get($MAIL_OPTIONS_SECURE, 'key'),
                                'value' => data_get($MAIL_OPTIONS_SECURE, 'value'),
                            ],
                        ]);
                    }
                    if ($MAIL_OPTIONS_PORT) {
                        $data = $data->merge([
                            'Mail Port' => [
                                'key' => data_get($MAIL_OPTIONS_PORT, 'key'),
                                'value' => data_get($MAIL_OPTIONS_PORT, 'value'),
                            ],
                        ]);
                    }
                    if ($MAIL_OPTIONS_SERVICE) {
                        $data = $data->merge([
                            'Mail Service' => [
                                'key' => data_get($MAIL_OPTIONS_SERVICE, 'key'),
                                'value' => data_get($MAIL_OPTIONS_SERVICE, 'value'),
                            ],
                        ]);
                    }
                    if ($MAIL_OPTIONS_HOST) {
                        $data = $data->merge([
                            'Mail Host' => [
                                'key' => data_get($MAIL_OPTIONS_HOST, 'key'),
                                'value' => data_get($MAIL_OPTIONS_HOST, 'value'),
                            ],
                        ]);
                    }

                    $fields->put('Ghost', $data);
                    break;
            }
        }
        $databases = $this->databases()->get();

        foreach ($databases as $database) {
            $image = str($database->image)->before(':')->value();
            switch ($image) {
                case str($image)->contains('postgres'):
                    $userVariables = ['SERVICE_USER_POSTGRES', 'SERVICE_USER_POSTGRESQL'];
                    $passwordVariables = ['SERVICE_PASSWORD_POSTGRES', 'SERVICE_PASSWORD_POSTGRESQL'];
                    $dbNameVariables = ['POSTGRESQL_DATABASE', 'POSTGRES_DB'];
                    $postgres_user = $this->environment_variables()->whereIn('key', $userVariables)->first();
                    $postgres_password = $this->environment_variables()->whereIn('key', $passwordVariables)->first();
                    $postgres_db_name = $this->environment_variables()->whereIn('key', $dbNameVariables)->first();
                    $data = collect([]);
                    if ($postgres_user) {
                        $data = $data->merge([
                            'User' => [
                                'key' => data_get($postgres_user, 'key'),
                                'value' => data_get($postgres_user, 'value'),
                                'rules' => 'required',
                            ],
                        ]);
                    }
                    if ($postgres_password) {
                        $data = $data->merge([
                            'Password' => [
                                'key' => data_get($postgres_password, 'key'),
                                'value' => data_get($postgres_password, 'value'),
                                'rules' => 'required',
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    if ($postgres_db_name) {
                        $data = $data->merge([
                            'Database Name' => [
                                'key' => data_get($postgres_db_name, 'key'),
                                'value' => data_get($postgres_db_name, 'value'),
                                'rules' => 'required',
                            ],
                        ]);
                    }
                    $fields->put('PostgreSQL', $data->toArray());
                    break;
                case str($image)->contains('mysql'):
                    $userVariables = ['SERVICE_USER_MYSQL', 'SERVICE_USER_WORDPRESS'];
                    $passwordVariables = ['SERVICE_PASSWORD_MYSQL', 'SERVICE_PASSWORD_WORDPRESS'];
                    $rootPasswordVariables = ['SERVICE_PASSWORD_MYSQLROOT', 'SERVICE_PASSWORD_ROOT'];
                    $dbNameVariables = ['MYSQL_DATABASE'];
                    $mysql_user = $this->environment_variables()->whereIn('key', $userVariables)->first();
                    $mysql_password = $this->environment_variables()->whereIn('key', $passwordVariables)->first();
                    $mysql_root_password = $this->environment_variables()->whereIn('key', $rootPasswordVariables)->first();
                    $mysql_db_name = $this->environment_variables()->whereIn('key', $dbNameVariables)->first();
                    $data = collect([]);
                    if ($mysql_user) {
                        $data = $data->merge([
                            'User' => [
                                'key' => data_get($mysql_user, 'key'),
                                'value' => data_get($mysql_user, 'value'),
                                'rules' => 'required',
                            ],
                        ]);
                    }
                    if ($mysql_password) {
                        $data = $data->merge([
                            'Password' => [
                                'key' => data_get($mysql_password, 'key'),
                                'value' => data_get($mysql_password, 'value'),
                                'rules' => 'required',
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    if ($mysql_root_password) {
                        $data = $data->merge([
                            'Root Password' => [
                                'key' => data_get($mysql_root_password, 'key'),
                                'value' => data_get($mysql_root_password, 'value'),
                                'rules' => 'required',
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    if ($mysql_db_name) {
                        $data = $data->merge([
                            'Database Name' => [
                                'key' => data_get($mysql_db_name, 'key'),
                                'value' => data_get($mysql_db_name, 'value'),
                                'rules' => 'required',
                            ],
                        ]);
                    }
                    $fields->put('MySQL', $data->toArray());
                    break;
                case str($image)->contains('mariadb'):
                    $userVariables = ['SERVICE_USER_MARIADB', 'SERVICE_USER_WORDPRESS', '_APP_DB_USER'];
                    $passwordVariables = ['SERVICE_PASSWORD_MARIADB', 'SERVICE_PASSWORD_WORDPRESS', '_APP_DB_PASS'];
                    $rootPasswordVariables = ['SERVICE_PASSWORD_MARIADBROOT', 'SERVICE_PASSWORD_ROOT', '_APP_DB_ROOT_PASS'];
                    $dbNameVariables = ['SERVICE_DATABASE_MARIADB', 'SERVICE_DATABASE_WORDPRESS', '_APP_DB_SCHEMA'];
                    $mariadb_user = $this->environment_variables()->whereIn('key', $userVariables)->first();
                    $mariadb_password = $this->environment_variables()->whereIn('key', $passwordVariables)->first();
                    $mariadb_root_password = $this->environment_variables()->whereIn('key', $rootPasswordVariables)->first();
                    $mariadb_db_name = $this->environment_variables()->whereIn('key', $dbNameVariables)->first();
                    $data = collect([]);

                    if ($mariadb_user) {
                        $data = $data->merge([
                            'User' => [
                                'key' => data_get($mariadb_user, 'key'),
                                'value' => data_get($mariadb_user, 'value'),
                                'rules' => 'required',
                            ],
                        ]);
                    }
                    if ($mariadb_password) {
                        $data = $data->merge([
                            'Password' => [
                                'key' => data_get($mariadb_password, 'key'),
                                'value' => data_get($mariadb_password, 'value'),
                                'rules' => 'required',
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    if ($mariadb_root_password) {
                        $data = $data->merge([
                            'Root Password' => [
                                'key' => data_get($mariadb_root_password, 'key'),
                                'value' => data_get($mariadb_root_password, 'value'),
                                'rules' => 'required',
                                'isPassword' => true,
                            ],
                        ]);
                    }
                    if ($mariadb_db_name) {
                        $data = $data->merge([
                            'Database Name' => [
                                'key' => data_get($mariadb_db_name, 'key'),
                                'value' => data_get($mariadb_db_name, 'value'),
                                'rules' => 'required',
                            ],
                        ]);
                    }
                    $fields->put('MariaDB', $data->toArray());
                    break;
            }
        }
        return $fields;
    }
    public function saveExtraFields($fields)
    {
        foreach ($fields as $field) {
            $key = data_get($field, 'key');
            $value = data_get($field, 'value');
            $found = $this->environment_variables()->where('key', $key)->first();
            if ($found) {
                $found->value = $value;
                $found->save();
            } else {
                $this->environment_variables()->create([
                    'key' => $key,
                    'value' => $value,
                    'is_build_time' => false,
                    'service_id' => $this->id,
                    'is_preview' => false,
                ]);
            }
        }
    }
    public function link()
    {
        if (data_get($this, 'environment.project.uuid')) {
            return route('project.service.configuration', [
                'project_uuid' => data_get($this, 'environment.project.uuid'),
                'environment_name' => data_get($this, 'environment.name'),
                'service_uuid' => data_get($this, 'uuid')
            ]);
        }
        return null;
    }
    public function documentation()
    {
        $services = getServiceTemplates();
        $service = data_get($services, str($this->name)->beforeLast('-')->value, []);
        return data_get($service, 'documentation', config('constants.docs.base_url'));
    }
    public function applications()
    {
        return $this->hasMany(ServiceApplication::class);
    }
    public function databases()
    {
        return $this->hasMany(ServiceDatabase::class);
    }
    public function destination()
    {
        return $this->morphTo();
    }
    public function environment()
    {
        return $this->belongsTo(Environment::class);
    }
    public function server()
    {
        return $this->belongsTo(Server::class);
    }
    public function byName(string $name)
    {
        $app = $this->applications()->whereName($name)->first();
        if ($app) {
            return $app;
        }
        $db = $this->databases()->whereName($name)->first();
        if ($db) {
            return $db;
        }
        return null;
    }
    public function scheduled_tasks(): HasMany
    {
        return $this->hasMany(ScheduledTask::class)->orderBy('name', 'asc');
    }
    public function environment_variables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class)->orderBy('key', 'asc');
    }
    public function environment_variables_preview(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class)->where('is_preview', true)->orderBy('key', 'asc');
    }
    public function workdir()
    {
        return service_configuration_dir() . "/{$this->uuid}";
    }
    public function saveComposeConfigs()
    {
        $workdir = $this->workdir();
        $commands[] = "mkdir -p $workdir";
        $commands[] = "cd $workdir";

        $docker_compose_base64 = base64_encode($this->docker_compose);
        $commands[] = "echo $docker_compose_base64 | base64 -d > docker-compose.yml";
        $envs = $this->environment_variables()->get();
        $commands[] = "rm -f .env || true";
        foreach ($envs as $env) {
            $commands[] = "echo '{$env->key}={$env->real_value}' >> .env";
        }
        if ($envs->count() === 0) {
            $commands[] = "touch .env";
        }
        instant_remote_process($commands, $this->server);
    }

    public function parse(bool $isNew = false): Collection
    {
        return parseDockerComposeFile($this, $isNew);
    }
    public function networks()
    {
        $networks = getTopLevelNetworks($this);
        // ray($networks);
        return $networks;
    }
}
