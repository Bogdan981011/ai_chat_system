from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
from requests.auth import HTTPBasicAuth
import os
from openai import OpenAI
import json
from dotenv import load_dotenv
import re

# Make sure your OpenAI API key is set in your environment variables.
load_dotenv()
client = OpenAI(
    # This is the default and can be omitted
    api_key=os.environ.get("OPENAI_API_KEY"),
)

def clean_json_string(json_string):
    # Remove trailing commas
    cleaned_string = re.sub(r',\s*}', '}', json_string)
    cleaned_string = re.sub(r',\s*]', ']', cleaned_string)
    return cleaned_string

def ai_decision_maker(user_message):
    """
    Uses GPT to decide on a task and create a formatted query.
    The prompt instructs GPT to return a JSON object with keys 'task' and 'formattedQuery'.
    """
    prompt = (
    # Task names
    f"There are 5 tasks to consider: 'country information', 'finding places', 'calculating distance between 2 or more points', 'weather details' and 'place details'.\n"

    # Formatted queries
    f"There are also 5 formatted queries to consider:\n"
    f"1. 'tell me about (country name in the user message)'\n"
    f"2. 'find (type of place to search, e.g., hospital, school, etc.) in (city name in the user message)'\n. Note: the type of place should be a always a singular noun.\n"
    f"3. 'route from (starting point specified in the user message, e.g., city name, name of a place, etc.) to (ending point specified in the user message, e.g., city name, name of a place, etc.). Note: the type of place should be a always a singular noun.'\n"
    f"4. 'weather in (city name specified in the user message)'\n"
    f"5. 'details concerning (type of place to search, e.g., hospital, school, etc.) in (city name specified in the user message). Note: the type of place should be a always a singular noun.'\n"

    # Informations for each task
    f"For each task, include the following additional details in the 'informations' field:\n"
    f" - For 'country information': list major attractions, describe the history and culture, explain the government and economy, and mention local cuisine.\n"
    f" - For 'finding places': provide a list of 5 best places based on reviews and ratings, and include details about additional services offered (include reviews/ratings and services details).\n"
    f" - For 'calculating distance between 2 or more points': mention available modes of transport, note any notable landmarks, and list stops or attractions along the route.\n"
    f" - For 'weather details': include additional metrics such as air quality, sunrise and sunset times, etc.\n"
    f" - For 'place details': list 5 places within the specified city and include their social media contacts or website links.\n\n"

    # GPT Request
    f"Based on the user message: '{user_message}', determine the appropriate task, create the correct formatted query, and provide the detailed informations as specified above.\n"
    f"Return only a JSON valid object with exactly three keys: 'task', 'formattedQuery' and 'informations'. The values of these keys need to be text strings.\n"
    f"Ensure the JSON is valid, well-structured and does not contain any unterminated strings or invalid characters.\n"
    )
    
    
    try:
        response = client.responses.create(
            model="gpt-3.5-turbo",  # or another model of your choice
            instructions="system",
            input=prompt,
            max_output_tokens=200,
            temperature=0.3
        )
        output_text = response.output_text.strip()
        cleaned_output = clean_json_string(output_text)
        # Try to parse GPT's output as JSON
        json_response = json.loads(cleaned_output)
    except Exception as e:
        # Fallback to a default response if something goes wrong.
        json_response = {
            "task": "problem",
            "formattedQuery": cleaned_output,
            "debug_error": str(e),
        }
    return json_response


app = Flask(__name__)
CORS(app)

@app.route('/decision', methods=['POST'])
def decision_endpoint():
    try:
        data = request.get_json(force=True)
        user_message = data.get("message", "")
        
        # Build decision (for example, a weather query)
        decision = ai_decision_maker(user_message)
        
        # Forward the decision to chatbot.php using Basic HTTP Authentication
        url = "http://localhost/payspedia_v3/pageweb/ai_chat/chatbot.php"
        user = 'myuser'
        password = 'password'
        execution_response = requests.post(url, 
                                           json=decision,
                                           auth=HTTPBasicAuth(user, password))
        
        # Parse JSON result from chatbot.php
        execution_result = execution_response.json()
    except Exception as e:
        execution_result = {"error": str(e)}
    
    return jsonify(execution_result)

if __name__ == '__main__':
    app.run(debug=False, host='0.0.0.0', port=5000)
