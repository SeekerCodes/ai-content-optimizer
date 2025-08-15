function aico_build_automotive_prompt($data) {
    $prompt = "You are a professional e-commerce copywriter for automotive and travel products in North America.

Generate a product title and 5 bullet points using FABE framework in JSON format.

Rules:
- Tone: Clear, benefit-driven, trustworthy
- Avoid fluff and hype
- Use real-world evidence when possible

Input:
Brand: {$data['brand']}
Product: {$data['product']}
Features: " . implode(', ', $data['features']) . "
Use Cases: " . implode(', ', $data['scenarios']) . "
Target Audience: {$data['audience']}

Output format:
{
  \"title\": \"...\",
  \"bullets\": [
    {\"feature\": \"\", \"advantage\": \"\", \"benefit\": \"\", \"evidence\": \"\"}
  ]
}";

    return $prompt;
}