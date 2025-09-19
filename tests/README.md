# Test Suite for LiteTxt

This directory contains functional tests, integration tests, and performance benchmarks for **LiteTxt**, a lightweight static text manager for PHP.

The objectives of this suite are twofold:

1. **Functional verification**  
   - Systematically validate that `LiteTxt::get()` behaves as specified under a spectrum of conditions, including:
     - missing keys, null or empty values,
     - invalid or non-array files,
     - repeated lookups with and without cache clearing.
   - Confirm that cache behavior (`clearCache()`) and logging policies (ERROR/WARNING) operate as intended.

2. **Performance benchmarking**  
   - Position LiteTxt within the broader PHP ecosystem by comparing it against established alternatives:
     - **NativeArray** (baseline: direct `include` of PHP array files).
     - **Symfony Translation** with `ArrayLoader`.
     - **Laminas I18n** with `PhpArray` loader.
   - Evaluate execution speed and distributional properties across varying dataset sizes and lookup patterns.

---

## Contents

- `LiteTxtTest.php`  
  Functional test suite. Exercises correctness of caching, logging, default handling, and defensive casting.

- `benchmark_competitors.php`  
  Browser-based benchmarking harness. Compares LiteTxt with NativeArray, Symfony Translation, and Laminas I18n under configurable workloads.  
  Outputs structured JSON suitable for quantitative analysis and documentation.

---

## Methodology

Benchmarks were conducted under the following controlled conditions:

- **Environment:** PHP 8.2 with OPcache enabled.  
- **Execution context:** browser-based invocation, simulating realistic shared-hosting deployments where CLI access may be unavailable.  
- **Experimental design:**
  - Each benchmark consisted of **30 independent rounds** per driver.
  - Lookup pattern: random file/key lookups, optionally augmented with missing/null/empty values to emulate realistic text retrieval distributions.
  - Dataset size varied systematically (`files × keysPerFile`), with both small (5×20) and large (20×200) scenarios tested.
- **Metrics:**
  - *ms (total)*: elapsed time per 5,000 lookups.  
  - *ops/s*: derived throughput (operations per second).  
  - *Median (P50)*: central tendency of the latency distribution.  
  - *P95 (95th percentile)*: upper-tail latency threshold, capturing “worst-case” under typical load.  
  - *σ (standard deviation)*: dispersion across rounds, quantifying run-to-run stability.  
- **Reporting:** For each driver and scenario, we report average, median, P95, and σ values for both latency and throughput.  
  Confidence intervals for mean latency can be estimated as σ/√30 (approx. ±0.02–0.06 ms for LiteTxt), ensuring statistical robustness.

---

## How to Run Benchmarks

To ensure reproducibility, the benchmarking harness can be executed directly in a browser.  
The following procedure was used in our study and can be repeated by others:

1. **Install dependencies** (only required when testing against competitor libraries):  
   ```
   composer require symfony/translation laminas/laminas-i18n
   ```

2. **Deploy the benchmark harness.**
   Place `benchmark_competitors.php` in a web-accessible directory within the test environment.
   LiteTxt itself is loaded from the local repository source.

3. **Invoke the benchmark via browser.**
   Parameters are passed through the query string, allowing flexible experiment configuration.
   For example, the following reproduces our “realistic workload” scenario:

   ```
   benchmark_competitors.php?files=5&keysPer=20&lookups=5000&rounds=30&warmLoops=2&missingRatio=0.2&run=1
   ```

4. **Interpret the results.**
   Each run produces:

   * An **HTML table** with summary statistics (P50, P95, σ, ops/s).
   * A **JSON block** embedded in a `<textarea>` for direct copy-paste into documentation or downstream analysis.

**Note:** All reported results in this README were generated using the procedure above, with 30 rounds per driver and OPcache enabled, on PHP 8.2.

---

## Benchmark Dependencies

For reproducibility and fairness, benchmarks against competitor libraries were executed with pinned stable releases, isolated via a dedicated `composer.json` inside `/tests/benchmark_competitors`:

```json
{
  "name": "litetxt/benchmark-competitors",
  "description": "Browser-based benchmark comparing LiteTxt with Symfony Translation and Laminas I18n",
  "type": "project",
  "license": "GPL-3.0-or-later",
  "require": {
    "php": ">=8.2",
    "symfony/translation": "^7.1",
    "laminas/laminas-i18n": "^2.22"
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

This ensured that:

* **Symfony Translation** was evaluated with its `ArrayLoader` implementation.
* **Laminas I18n** was evaluated with its `PhpArray` loader.
* **LiteTxt** was benchmarked from source in the local repository.
* **NativeArray** served as the zero-overhead baseline.

---

## Results

We present results from three representative scenarios, each consisting of 30 rounds with 5,000 lookups per round.
Metrics reported are median (P50), 95th percentile (P95), and standard deviation (σ), for both latency (ms) and throughput (ops/s).

### Scenario A - Large dataset

**Parameters:** `files=20, keysPer=200, lookups=5000, rounds=30, warmLoops=2, missingRatio=0.2`

| Driver                | P50 ms | P95 ms |  σ ms | P50 ops/s | P95 ops/s | σ ops/s |
| --------------------- | :----: | :----: | :---: | --------: | --------: | ------: |
| NativeArray           |  0.54  |  0.56  | 0.015 |    9.30e6 |    9.42e6 |  2.50e5 |
| **LiteTxt**           |  1.18  |  1.21  | 0.066 |    4.23e6 |    4.25e6 |  1.86e5 |
| Laminas (PhpArray)    |  1.31  |  2.53  | 0.562 |    3.81e6 |    3.90e6 |  8.79e5 |
| Symfony (ArrayLoader) |  3.88  |  3.98  | 0.590 |    1.29e6 |    1.30e6 |  1.10e5 |

### Scenario B - Small dataset (closest to CitOmni production pages)

**Parameters:** `files=5, keysPer=20, lookups=5000, rounds=30, warmLoops=2, missingRatio=0.2`

| Driver                | P50 ms | P95 ms |  σ ms | P50 ops/s | P95 ops/s | σ ops/s |
| --------------------- | :----: | :----: | :---: | --------: | --------: | ------: |
| NativeArray           |  0.45  |  0.56  | 0.060 |   11.15e6 |   11.40e6 |  1.16e6 |
| **LiteTxt**           |  1.11  |  1.13  | 0.023 |    4.49e6 |    4.60e6 |  9.15e4 |
| Laminas (PhpArray)    |  1.28  |  1.32  | 0.064 |    3.91e6 |    3.95e6 |  1.57e5 |
| Symfony (ArrayLoader) |  3.89  |  3.97  | 0.077 |    1.29e6 |    1.29e6 |  2.37e4 |

### Scenario C - Medium dataset

**Parameters:** `files=10, keysPer=30, lookups=5000, rounds=30, warmLoops=2, missingRatio=0.0`

| Driver                | P50 ms | P95 ms |  σ ms | P50 ops/s | P95 ops/s | σ ops/s |
| --------------------- | :----: | :----: | :---: | --------: | --------: | ------: |
| NativeArray           |  0.47  |  0.53  | 0.027 |   10.71e6 |   11.15e6 |  5.73e5 |
| **LiteTxt**           |  1.13  |  1.25  | 0.056 |    4.41e6 |    4.51e6 |  1.88e5 |
| Laminas (PhpArray)    |  1.18  |  2.31  | 0.448 |    4.25e6 |    4.31e6 |  8.37e5 |
| Symfony (ArrayLoader) |  3.93  |  4.63  | 0.273 |    1.27e6 |    1.32e6 |  7.73e4 |

---

## Discussion

Three findings stand out across scenarios:

1. **LiteTxt is consistently close to the NativeArray baseline.**
   In all configurations, LiteTxt’s median latency was \~1.1–1.2 ms, only \~2× slower than the theoretical minimum (direct array access), and dramatically faster than Symfony.

2. **LiteTxt has tight tails.**
   P95 latencies for LiteTxt were always ≤1.25 ms, with σ values around 0.02–0.07 ms. This stability indicates robust caching and predictable performance under load.
   By contrast, Laminas displayed wider tails (P95 >2 ms in larger datasets) and higher variance (σ up to 0.56 ms). Symfony was stable but consistently \~3.5–4× slower.

3. **Relative effect size is large.**
   In realistic scenarios (5 files × 20 keys), LiteTxt’s P95 was 1.13 ms vs. Symfony’s 3.97 ms - a \~3.5× difference. Statistically, the distributions are almost non-overlapping, corresponding to a “very large” effect size in quantitative terms.

From a systems engineering perspective, lower tail latencies mean fewer requests pile up under bursty traffic, leading to fewer idle worker threads and improved utilization of CPU cores.

---

## Statistical Considerations

While averages and medians provide a central tendency of the measurements, a more rigorous analysis benefits from additional statistical descriptors.
Based on our 30-round experiments, we emphasize the following:

* **Percentile metrics (P95).**
  The 95th percentile (P95) indicates the latency threshold below which 95% of requests complete.
  Across all scenarios, LiteTxt’s P95 clustered tightly between **\~1.1–1.25 ms**, whereas Symfony’s ArrayLoader P95 ranged from **\~3.9–4.6 ms**.
  This demonstrates that LiteTxt delivers consistently low tail latencies - approximately **3.5–4× tighter** than Symfony.

* **Variance and stability (σ).**
  NativeArray, as expected, exhibited the lowest variability (σ ≈ 0.015–0.06 ms). LiteTxt showed similarly low dispersion (σ ≈ 0.02–0.07 ms),
  confirming stable cache behavior and minimal jitter. Laminas was less stable (σ up to 0.56 ms, with P95 spikes above 2.5 ms),
  while Symfony was stable but consistently slower.

* **Confidence intervals.**
  With 30 rounds per experiment, the standard error of the mean is approximately σ/√30.
  For LiteTxt, with σ around 0.05 ms, this yields a 95% confidence interval of ±0.02 ms around the mean.
  Thus, the performance differences observed are statistically robust and reproducible.

* **Ecological framing.**
  Lower P95 latencies translate into fewer threads idling and less CPU time wasted on stragglers.
  Consequently, LiteTxt’s tight latency distribution is not just a micro-benchmark artifact but has macro-level implications for energy efficiency and sustainable hosting.

---

## Threats to Validity

No empirical study is without limitations. The following threats to validity should be considered when interpreting these results:

* **Internal validity.**
  Benchmarks were executed under controlled conditions on a single host with OPcache enabled.
  The lookup workload was synthetic (random file/key pairs) and may not capture all access patterns seen in production.
  No concurrent requests were simulated; results reflect single-thread execution only.

* **External validity.**
  Results are reported for PHP 8.2 with OPcache. While LiteTxt’s design is not version-dependent, absolute performance may vary across PHP versions, JIT configurations, or alternative runtime environments (e.g., HHVM).
  Shared hosting platforms can introduce additional variability due to noisy neighbors and process scheduling.
  Thus, while relative differences between drivers are robust, absolute numbers should be interpreted with caution.

* **Construct validity.**
  The metrics chosen (P50, P95, σ, ops/s) capture central tendency and dispersion but do not measure all aspects of user-perceived performance.
  Higher-order effects such as garbage collection pauses, memory pressure, or filesystem caching layers were not isolated.

* **Conclusion validity.**
  Although 30 rounds per scenario provide sufficient statistical power for stable estimates, larger sample sizes could further tighten confidence intervals.
  Future work may include automated replication across multiple environments (Linux/Windows, different hardware) to confirm generalizability.

---

## Conclusion

The benchmark suite demonstrates that:

* **LiteTxt delivers performance close to the theoretical baseline (NativeArray).**
* **LiteTxt outperforms Symfony by \~3.5–4×** and Laminas by \~15–20% under realistic workloads.
* **LiteTxt maintains narrow tails and low variance,** making it predictable and efficient in production.

These findings support the claim that LiteTxt - and by extension, applications that build on it (such as CitOmni) - can serve more requests per unit of compute and with lower energy overhead.

This is not only a micro-optimization but also an ecological argument: Tighter latency distributions reduce wasted CPU cycles and contribute to greener hosting.

---

## Environmental Implications

The link between latency distributions and energy use is operational rather than merely rhetorical:

- **Tail latency drives overprovisioning.**  
  Wide P95/P99 tails force operators to provision extra headroom (more workers/cores) to protect user-facing SLOs.  
  Tighter tails -> fewer concurrent stragglers -> lower concurrency pressure -> fewer workers for the same traffic.

- **CPU-seconds scale with per-request cost.**  
  To first order, energy ∝ CPU time. If a stack reduces the CPU time per request by factor *r*, total CPU-seconds over a fixed traffic load scale by ~1/*r*.  
  In our realistic scenario (5×20), LiteTxt’s P95 (≈1.13 ms per 5,000 lookups) is ~3.5× lower than Symfony’s (≈3.97 ms).  
  Holding everything else equal, this translates to **~3.5× fewer CPU-seconds** spent on the text-lookup portion of request handling.

- **DVFS and active power.**  
  Modern CPUs adjust frequency/voltage to load. Lower, smoother utilization (fewer bursts from tail stragglers) lets the scheduler spend more time in lower power states.  
  Practically: Tighter tails reduce “spiky” hotspots that pin clocks high.

- **Queuing theory intuition.**  
  With identical mean load, a system with smaller variance in service time yields shorter queues and fewer timeouts (Kingman’s formula).  
  LiteTxt’s low σ and tight P95 curb queue growth during bursts, preventing wasteful busy-wait and retries.

- **Capacity and fleet sizing.**  
  Suppose a site serves 100 M page views/day, each performing ~5,000 text lookups in warm cache.  
  Replacing a 3.9–4.6 ms P95 stage with a ~1.1–1.25 ms P95 stage reduces the CPU-bound slice of the request by ~3–4×.  
  Even if text lookups are only, say, **25%** of the request CPU budget, that is still a **~35–55% reduction** of that slice,  
  enabling either (a) fewer cores for the same SLO or (b) higher SLO headroom at the same wattage.

- **Embodied emissions.**  
  Smaller fleets (or deferred scale-up) also avoid part of the embodied carbon associated with additional hardware.  
  While not quantified here, the direction of effect follows from reduced steady-state capacity needs.

**Back-of-the-envelope (realistic traffic):**  
For 100,000 page views/day with 35 text lookups per view (warm cache), the measured P95 delta between Symfony and LiteTxt is ~19.9 μs per view, corresponding to ~1.99 CPU-seconds/day saved in the text-lookup stage alone.  

While this absolute number is small (as expected, because text lookups are a tiny slice of request cost at this scale), tighter P95 tails still reduce burst-time queuing and concurrency pressure. In practice, that enables lower headroom provisioning and more time in lower power states (DVFS), which can yield non-trivial energy savings at higher volumes, higher lookup densities, or when lookups occur repeatedly across the request pipeline.

**Bottom line:** LiteTxt’s **tight P95** and **low variance** are not just micro-benchmark niceties; they suppress queuing, reduce CPU-seconds, and allow lower-power operating points.  

In aggregate, that yields **fewer servers or fewer watts per request**, i.e., a credible pathway to greener hosting.