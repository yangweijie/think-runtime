-- simple_keepalive.lua
-- 简化的 wrk Lua 脚本：测试 Workerman Keep-Alive 性能

wrk.method = "GET"
wrk.headers["Connection"] = "keep-alive"
wrk.headers["Keep-Alive"] = "timeout=60, max=1000"
wrk.headers["Accept-Encoding"] = "gzip, deflate"
wrk.headers["User-Agent"] = "wrk-keepalive-test/1.0"
wrk.headers["Accept"] = "application/json"

-- 全局统计变量
local stats = {
    requests = 0,
    errors = 0,
    keepalive_responses = 0,
    gzip_responses = 0,
    total_bytes = 0
}

-- 请求初始化
function init(args)
    print("=== Workerman Keep-Alive 简化测试 ===")
    print("Keep-Alive: 启用")
    print("Gzip: 启用")
    print("开始测试...\n")
end

-- 每个请求前调用
function request()
    stats.requests = stats.requests + 1
    return wrk.format(wrk.method, wrk.path, wrk.headers, wrk.body)
end

-- 每个响应后调用
function response(status, headers, body)
    if status ~= 200 then
        stats.errors = stats.errors + 1
        return
    end
    
    -- 检查是否使用了 keep-alive
    local connection = headers["Connection"] or headers["connection"] or ""
    if string.find(string.lower(connection), "keep-alive") then
        stats.keepalive_responses = stats.keepalive_responses + 1
    end

    -- 检查是否使用了 gzip 压缩
    local encoding = headers["Content-Encoding"] or headers["content-encoding"] or ""
    if string.find(string.lower(encoding), "gzip") then
        stats.gzip_responses = stats.gzip_responses + 1
    end
    
    -- 统计字节数
    stats.total_bytes = stats.total_bytes + string.len(body)
end

-- 测试完成后调用
function done(summary, latency, requests)
    print("\n" .. string.rep("=", 60))
    print("           Workerman Keep-Alive 测试结果")
    print(string.rep("=", 60))
    
    -- 基础统计
    local total_errors = summary.errors.connect + summary.errors.read + summary.errors.write + summary.errors.timeout
    local success_requests = summary.requests - total_errors
    local duration_seconds = summary.duration / 1000000
    
    print(string.format("总请求数:     %d", summary.requests))
    print(string.format("成功请求:     %d", success_requests))
    print(string.format("失败请求:     %d", total_errors))
    print(string.format("测试时长:     %.2fs", duration_seconds))
    
    -- QPS 统计
    local qps = summary.requests / duration_seconds
    print(string.format("QPS:          %.2f", qps))
    
    -- 延迟统计
    print(string.format("平均延迟:     %.2fms", latency.mean / 1000))
    print(string.format("50%% 延迟:     %.2fms", latency["50"] / 1000))
    print(string.format("90%% 延迟:     %.2fms", latency["90"] / 1000))
    print(string.format("99%% 延迟:     %.2fms", latency["99"] / 1000))
    print(string.format("最大延迟:     %.2fms", latency.max / 1000))
    
    -- Keep-Alive 统计
    if summary.requests > 0 then
        local keepalive_rate = (stats.keepalive_responses / summary.requests) * 100
        print(string.format("Keep-Alive:   %.1f%% (%d/%d)", keepalive_rate, stats.keepalive_responses, summary.requests))
        
        -- Gzip 压缩统计
        local gzip_rate = (stats.gzip_responses / summary.requests) * 100
        print(string.format("Gzip 压缩:    %.1f%% (%d/%d)", gzip_rate, stats.gzip_responses, summary.requests))
    end
    
    -- 数据传输统计
    local total_mb = stats.total_bytes / 1024 / 1024
    local throughput = total_mb / duration_seconds
    print(string.format("传输数据:     %.2f MB", total_mb))
    print(string.format("传输速率:     %.2f MB/s", throughput))
    
    print(string.rep("=", 60))
    
    -- 性能评级
    print("\n性能评级:")
    if qps > 10000 then
        print("🚀 优秀 (QPS > 10,000)")
    elseif qps > 5000 then
        print("✅ 良好 (QPS > 5,000)")
    elseif qps > 1000 then
        print("⚠️  一般 (QPS > 1,000)")
    else
        print("❌ 需要优化 (QPS < 1,000)")
    end
    
    -- Keep-Alive 效果评估
    if summary.requests > 0 then
        local keepalive_rate = (stats.keepalive_responses / summary.requests) * 100
        if keepalive_rate > 90 then
            print("🔗 Keep-Alive 效果优秀")
        elseif keepalive_rate > 70 then
            print("🔗 Keep-Alive 效果良好")
        else
            print("🔗 Keep-Alive 效果需要改进")
        end
        
        -- Gzip 效果评估
        local gzip_rate = (stats.gzip_responses / summary.requests) * 100
        if gzip_rate > 80 then
            print("📦 Gzip 压缩效果优秀")
        elseif gzip_rate > 50 then
            print("📦 Gzip 压缩效果良好")
        else
            print("📦 Gzip 压缩效果需要改进")
        end
    end
    
    print("\n测试完成！")
end
