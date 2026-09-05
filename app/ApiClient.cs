using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;

namespace SalesBISync;

public class ApiClient
{
    private readonly HttpClient _httpClient;

    public ApiClient(int timeoutSeconds = 90)
    {
        _httpClient = new HttpClient
        {
            Timeout = TimeSpan.FromSeconds(timeoutSeconds)
        };
        _httpClient.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
    }

    public async Task<(bool Success, string Message, SyncResponse? Response)> PostSyncDataAsync(
        string serverUrl,
        string apiKey,
        SyncPayload payload)
    {
        if (string.IsNullOrWhiteSpace(serverUrl))
        {
            return (false, "Server URL is not configured in config.json.", null);
        }

        if (string.IsNullOrWhiteSpace(apiKey))
        {
            return (false, "API Key is not configured in config.json.", null);
        }

        try
        {
            string json = JsonSerializer.Serialize(payload);
            using var request = new HttpRequestMessage(HttpMethod.Post, serverUrl)
            {
                Content = new StringContent(json, Encoding.UTF8, "application/json")
            };

            request.Headers.Add("X-API-KEY", apiKey);

            using var response = await _httpClient.SendAsync(request);
            string responseBody = await response.Content.ReadAsStringAsync();

            if (!response.IsSuccessStatusCode)
            {
                string errorDetail = responseBody;
                try
                {
                    var errObj = JsonSerializer.Deserialize<SyncResponse>(responseBody);
                    if (!string.IsNullOrWhiteSpace(errObj?.Message))
                    {
                        errorDetail = errObj.Message;
                    }
                }
                catch
                {
                    // Ignore JSON parse errors on error responses
                }

                return (false, $"HTTP {(int)response.StatusCode} ({response.ReasonPhrase}): {errorDetail}", null);
            }

            var syncResponse = JsonSerializer.Deserialize<SyncResponse>(responseBody);
            if (syncResponse != null && syncResponse.Success)
            {
                return (true, syncResponse.Message, syncResponse);
            }

            return (false, syncResponse?.Message ?? "Server returned unsuccessful response.", syncResponse);
        }
        catch (TaskCanceledException)
        {
            return (false, "Connection timed out. Check your server URL and network connection.", null);
        }
        catch (HttpRequestException ex)
        {
            return (false, $"Network error: {ex.Message}", null);
        }
        catch (Exception ex)
        {
            return (false, $"Unexpected API error: {ex.Message}", null);
        }
    }

    public async Task<(bool Success, string Message)> TestApiConnectivityAsync(string serverUrl, string apiKey)
    {
        var dummyPayload = new SyncPayload
        {
            Invoices = new List<InvoiceRecord>(),
            Payments = new List<PaymentRecord>()
        };

        var (success, message, response) = await PostSyncDataAsync(serverUrl, apiKey, dummyPayload);
        return (success, message);
    }
}
