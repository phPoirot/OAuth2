<?php
namespace Poirot\OAuth2\Server\Grant;

use Poirot\OAuth2\Interfaces\Server\Repository\iOAuthUser;
use Poirot\OAuth2\Server\Exception\exOAuthServer;
use Poirot\OAuth2\Server\Response\GrantResponseRedirect;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class GrantImplicit
    extends aGrant
{
    const GrantType = 'implicit';

    /** @var callable */
    protected $retrieveUserCallback;

    
    /**
     * Grant identifier (client_credentials, password, ...)
     *
     * @return string
     */
    function getGrantType()
    {
        return self::GrantType;
    }

    /**
     * Can This Grant Respond To Request
     *
     * - usually it match against "grant_type" request
     *
     * @param ServerRequestInterface $request
     *
     * @return boolean
     */
    function canRespondToRequest(ServerRequestInterface $request)
    {
        $return = false;

        $requestParameters = $request->getQueryParams();
        $responseType      = $requestParameters['response_type'] ?? null;
        $clientIdentifier  = $requestParameters['client_id'] ?? null;

        if ($responseType === 'token' && $clientIdentifier !== null) {
            $return = clone $this;
            $return->request = $request;
        }

        return $return;
    }

    /**
     * Respond To Grant Request
     *
     * note: We consider that user approved the grant
     *       when respond() called...
     *       otherwise the handle of deny is on behalf of
     *       application structure. maybe you want throw exAccessDenied
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface prepared response
     * @throws exOAuthServer
     */
    function respond(ResponseInterface $response)
    {
        $request = $this->request;

        $client = $this->assertClient(false);
        list($scopeRequested, $scopes) = $this->assertScopes($client->getScope());

        // The user approved the client, redirect them back with an access token
        $user = $this->getUserEntity();

        $reqParams = $request->getQueryParams();
        $redirect  = $reqParams['redirect_uri'] ?? null;
        $redirect  = $redirect ?? current($client->getRedirectUri());
        $state     = $reqParams['state'] ?? null;

        $accToken  = $this->issueAccessToken($client, $this->getTtlAccessToken(), $user, $scopes);

        $grantResponse = $this->newGrantResponse();
        $grantResponse->setAccessToken($accToken);
        $grantResponse->import(array('state' => $state));
        $grantResponse->setRedirectUri($redirect);
        if (array_diff($scopeRequested, $scopes))
            // the issued access token scope is different from the
            // one requested by the client, include the "scope"
            // response parameter to inform the client of the
            // actual scope granted.
            $grantResponse->import(array(
                'scope' => implode(' ' /* Scope Delimiter */, $scopes),
            ));
        
        $response = $grantResponse->toResponseWith($response);
        return $response;
    }

    /**
     * New Grant Response
     * @return GrantResponseRedirect
     * @throws \Exception|exOAuthServer
     */
    function newGrantResponse()
    {
        $request = $this->request;

        $client    = $this->assertClient();
        $reqParams = $request->getQueryParams();
        $redirectUri  = $reqParams['redirect_uri'] ?? null;
        if ($redirectUri === null) {
            $redirectUri = $client->getRedirectUri();
            if (is_array($redirectUri))
                $redirectUri = current($redirectUri);
            elseif (\Poirot\Std\isStringify($redirectUri))
                $redirectUri = (string) $redirectUri;
            else
                throw new \Exception('Invalid Redirect Uri Provided by Client.');
        }

        // TODO what about assert clients??
        if ($redirectUri !== null) {
            $redirectUri = rtrim($redirectUri, '/');
            $match = false;
            foreach ($client->getRedirectUri() as $registeredRedirect) {
                $registeredRedirect = rtrim($registeredRedirect, '/');
                if ($redirectUri == $registeredRedirect) {
                    $match = true;
                    break;
                }
            }

            if ( !$match )
                ## redirect-uri not match
                // So we must not redirect back the error result to client
                // responder as an argument are abandoned!!
                throw exOAuthServer::invalidClient();
        }


        $grantRespose = new GrantResponseRedirect();
        $grantRespose->setRedirectUri($redirectUri);
        return $grantRespose;
    }


    // Options:

    /**
     * Set Callable To Retrieve Authenticated User Entity Object
     *
     * @param callable $callable
     *
     * @return $this
     */
    function setRetrieveUserCallback(/*callable*/ $callable)
    {
        if (!is_callable($callable))
            throw new \InvalidArgumentException(sprintf(
                'User Callback Must Be Callable; given: (%s).'
                , \Poirot\Std\flatten($callable)
            ));

        $this->retrieveUserCallback = $callable;
        return $this;
    }

    /**
     * User Entity
     * @return iOAuthUser
     * @throws \Exception|exOAuthServer
     */
    function getUserEntity()
    {
        if (!$this->retrieveUserCallback)
            throw new \Exception('User Retrieve Callback Not Set.');

        $user = call_user_func($this->retrieveUserCallback);
        if (!$user instanceof iOAuthUser)
            throw exOAuthServer::accessDenied($this->newGrantResponse());

        return $user;
    }
}
