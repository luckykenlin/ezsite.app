import { GoogleGenAI } from "@google/genai";

const API_KEY = process.env.API_KEY || '';

export const getFortune = async (): Promise<string> => {
  if (!API_KEY) {
    console.warn("Gemini API Key not found. Returning default fortune.");
    return "A journey of a thousand miles begins with a single delicious meal.";
  }

  try {
    const ai = new GoogleGenAI({ apiKey: API_KEY });
    const response = await ai.models.generateContent({
      model: 'gemini-2.5-flash',
      contents: "Generate a short, cryptic but uplifting fortune cookie message. It should be one sentence. Do not include quotes.",
    });
    
    return response.text || "Luck is what happens when preparation meets opportunity.";
  } catch (error) {
    console.error("Error fetching fortune:", error);
    return "Good things come to those who wait.";
  }
};
